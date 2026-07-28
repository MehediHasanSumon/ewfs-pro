<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\CompanySetting;
use App\Models\CreditSaleCustomer;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Product;
use App\Models\SMSLog;
use App\Models\SMSSetting;
use App\Models\SMSTemplate;
use App\Models\Vehicle;
use App\Services\DocumentNumberService;
use App\Services\OpeningBalanceService;
use App\Services\PartyAccountService;
use App\Services\PartyLedgerService;
use App\Services\VehicleProductAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly OpeningBalanceService $openingBalances,
        private readonly PartyAccountService $partyAccounts,
        private readonly PartyLedgerService $partyLedger,
        private readonly VehicleProductAssignmentService $vehicleProducts
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-customer', only: ['index', 'show', 'statement', 'downloadSalesPdf', 'downloadPaymentsPdf']),
            new Middleware('permission:view-customer|can-customer-download', only: ['downloadPdf']),
            new Middleware('permission:create-customer', only: ['store']),
            new Middleware('permission:update-customer', only: ['update']),
            new Middleware('permission:delete-customer', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:view-customer', only: ['sendSMS']),
        ];
    }

    public function index(Request $request)
    {
        $query = Customer::select('id', 'account_id', 'code', 'name', 'proprietor_name', 'mobile', 'email', 'nid_number', 'vat_reg_no', 'tin_no', 'trade_license', 'discount_rate', 'credit_limit', 'credit_days', 'address', 'status', 'created_at')
            ->with('account:id,name,ac_number,group_id', 'account.group:id,code,name');

        // Apply filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('proprietor_name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%')
                    ->orWhere('mobile', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'code', 'name', 'proprietor_name', 'mobile', 'email', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, min((int) $request->get('per_page', 10), 100));
        $customers = $query->paginate($perPage)->withQueryString();
        $metrics = $this->partyLedger->customerMetrics($customers->getCollection());
        $customers->setCollection(
            $customers->getCollection()->map(function (Customer $customer) use ($metrics) {
                $metric = $metrics->get($customer->id);

                return [
                    'id' => $customer->id,
                    'account_id' => $customer->account_id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'proprietor_name' => $customer->proprietor_name,
                    'mobile' => $customer->mobile,
                    'email' => $customer->email,
                    'nid_number' => $customer->nid_number,
                    'vat_reg_no' => $customer->vat_reg_no,
                    'tin_no' => $customer->tin_no,
                    'trade_license' => $customer->trade_license,
                    'discount_rate' => $customer->discount_rate,
                    'security_deposit' => $metric['security_deposit'],
                    'credit_limit' => $customer->credit_limit,
                    'address' => $customer->address,
                    'status' => $customer->status,
                    'account' => $customer->account,
                    'total_sales' => $metric['total_sales'],
                    'total_paid' => $metric['total_paid'],
                    'current_due' => $metric['current_due'],
                    'created_at' => $customer->created_at->format('Y-m-d'),
                ];
            })
        );

        $groups = Group::where('status', true)->get(['id', 'code', 'name']);
        $products = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_name as name']);

        $lastCustomerGroup = null;
        $lastCustomer = Customer::with('account.group')->latest()->first();
        if ($lastCustomer && $lastCustomer->account && $lastCustomer->account->group) {
            $lastCustomerGroup = [
                'id' => $lastCustomer->account->group->id,
                'code' => $lastCustomer->account->group->code,
            ];
        }

        return Inertia::render('Customers/Customers', [
            'customers' => $customers,
            'groups' => $groups,
            'products' => $products,
            'vehicleProductLimit' => max(1, (int) config('erp.vehicle_products.max_assigned', 50)),
            'lastCustomerGroup' => $lastCustomerGroup,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(CustomerRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $status = (bool) ($validated['status'] ?? true);
            $account = $this->partyAccounts->createCustomerAccount($validated['name'], $status);
            $customer = Customer::query()->create([
                'account_id' => $account->id,
                'code' => $this->numbers->next('customer', 'CC', null, 3),
                'name' => $validated['name'],
                'proprietor_name' => $validated['proprietor_name'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'nid_number' => $validated['nid_number'] ?? null,
                'vat_reg_no' => $validated['vat_reg_no'] ?? null,
                'tin_no' => $validated['tin_no'] ?? null,
                'trade_license' => $validated['trade_license'] ?? null,
                'discount_rate' => $validated['discount_rate'] ?? 0,
                'credit_limit' => $validated['credit_limit'] ?? 0,
                'address' => $validated['address'] ?? null,
                'status' => $status,
            ]);

            if (! empty($validated['vehicle_number'])) {
                $vehicle = Vehicle::query()->create([
                    'customer_id' => $customer->id,
                    'vehicle_type' => $validated['vehicle_type'] ?? null,
                    'vehicle_name' => $validated['vehicle_name'] ?? null,
                    'vehicle_number' => $validated['vehicle_number'],
                    'reg_date' => $validated['reg_date'] ?? null,
                    'status' => $status,
                ]);

                $this->vehicleProducts->sync($vehicle, $validated['products'] ?? []);
            }

            $businessDate = now()->toDateString();
            $this->openingBalances->customerPreviousDue(
                $customer,
                (float) ($validated['previous_due'] ?? 0),
                $businessDate
            );
            $this->openingBalances->customerDeposit(
                $customer,
                (float) ($validated['security_deposit'] ?? 0),
                $businessDate
            );
        });

        return redirect()->back()->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'account:id,name,ac_number',
            'vehicles:id,customer_id,vehicle_number,vehicle_name,vehicle_type,reg_date,status,created_at',
            'vehicles.products:id,product_name',
        ]);

        $paymentQuery = $this->partyLedger->vouchers(
            'customer_id',
            $customer->id,
            'receipt'
        );
        $paymentCount = (clone $paymentQuery)->count();
        $recentPayments = $this->partyLedger->voucherRows(
            $paymentQuery
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'Received'
        );

        $recentSales = CreditSaleCustomer::query()
            ->where('customer_id', $customer->id)
            ->whereHas('creditSale', fn ($sale) => $sale->posted())
            ->with([
                'creditSale:id,sale_date,invoice_no,status',
                'items:id,credit_sale_customer_id,vehicle_id,vehicle_number_snapshot,quantity',
                'items.vehicle:id,vehicle_number',
            ])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function (CreditSaleCustomer $allocation) {
                $item = $allocation->items->first();

                return [
                    'id' => $allocation->id,
                    'date' => $allocation->creditSale?->sale_date?->format('Y-m-d'),
                    'amount' => (float) $allocation->grand_total,
                    'quantity' => (float) $allocation->items->sum('quantity'),
                    'vehicle_number' => $item?->vehicle?->vehicle_number
                        ?? $item?->vehicle_number_snapshot
                        ?? 'N/A',
                    'invoice_no' => $allocation->creditSale?->invoice_no,
                    'status' => $allocation->creditSale?->status,
                ];
            });

        $metric = $this->partyLedger
            ->customerMetrics(collect([$customer]))
            ->get($customer->id);

        $smsTemplates = SMSTemplate::where('status', true)
            ->select('id', 'title', 'type', 'message')
            ->get();

        return Inertia::render('Customers/CustomerDetails', [
            'customer' => array_merge(
                (new CustomerResource($customer))->resolve(),
                [
                    'security_deposit' => $metric['security_deposit'],
                ]
            ),
            'recentPayments' => $recentPayments,
            'recentSales' => $recentSales,
            'totalSales' => $metric['total_sales'],
            'salesCount' => $metric['sales_count'],
            'totalPaid' => $metric['total_paid'],
            'paymentCount' => $paymentCount,
            'currentDue' => $metric['current_due'],
            'smsTemplates' => $smsTemplates,
        ]);
    }

    public function statement(Request $request, Customer $customer)
    {
        $customer->load('account:id,name,ac_number');

        $year = $request->get('year', date('Y'));

        $transactions = $this->partyLedger->statement($customer->account, 'customer');
        $metric = $this->partyLedger
            ->customerMetrics(collect([$customer]))
            ->get($customer->id);
        $monthlySales = $this->partyLedger->customerMonthlySales(
            $customer->id,
            (int) $year
        );
        $availableYears = $this->partyLedger->customerSalesYears($customer->id);
        $recentPayments = $this->partyLedger->paginatedVoucherRows(
            $this->partyLedger->vouchers(
                'customer_id',
                $customer->id,
                'receipt',
                $request->start_date,
                $request->end_date
            ),
            10,
            'Received'
        );

        return Inertia::render('Customers/CustomerStatement', [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'address' => $customer->address,
                'security_deposit' => $metric['security_deposit'],
                'account' => $customer->account,
            ],
            'transactions' => $transactions,
            'currentBalance' => $metric['current_due'],
            'monthlySales' => $monthlySales,
            'availableYears' => $availableYears,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $customer) {
            $status = (bool) ($validated['status'] ?? true);
            $customer->loadMissing('account');
            $customer->account?->update([
                'name' => $validated['name'],
                'status' => $status,
            ]);

            $customer->update([
                'code' => $validated['code'] ?? $customer->code,
                'name' => $validated['name'],
                'proprietor_name' => $validated['proprietor_name'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'nid_number' => $validated['nid_number'] ?? null,
                'vat_reg_no' => $validated['vat_reg_no'] ?? null,
                'tin_no' => $validated['tin_no'] ?? null,
                'trade_license' => $validated['trade_license'] ?? null,
                'discount_rate' => $validated['discount_rate'] ?? 0,
                'credit_limit' => $validated['credit_limit'] ?? 0,
                'address' => $validated['address'] ?? null,
                'status' => $status,
            ]);

            $businessDate = now()->toDateString();

            if (array_key_exists('previous_due', $validated)) {
                $this->openingBalances->setCustomerPreviousDue(
                    $customer,
                    (float) ($validated['previous_due'] ?? 0),
                    $businessDate
                );
            }

            if (array_key_exists('security_deposit', $validated)) {
                $this->openingBalances->setCustomerDeposit(
                    $customer,
                    (float) ($validated['security_deposit'] ?? 0),
                    $businessDate
                );
            }
        });

        return redirect()->back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->deleteCustomer($customer);

        return redirect()->back()->with('success', 'Customer deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:customers,id',
        ]);

        $customers = Customer::query()
            ->whereIn('id', $request->ids)
            ->with(['account', 'vehicles.products'])
            ->get();

        DB::transaction(function () use ($customers) {
            foreach ($customers as $customer) {
                $this->assertCustomerCanBeDeleted($customer);
            }

            foreach ($customers as $customer) {
                foreach ($customer->vehicles as $vehicle) {
                    $vehicle->products()->detach();
                    $vehicle->delete();
                }

                $account = $customer->account;
                $customer->delete();
                $account?->delete();
            }
        });

        return redirect()->back()->with('success', count($request->ids).' customers deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = Customer::select('id', 'account_id', 'code', 'name', 'proprietor_name', 'mobile', 'email', 'status', 'created_at')
            ->with('account:id,name,ac_number');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('proprietor_name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%')
                    ->orWhere('mobile', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'code', 'name', 'proprietor_name', 'mobile', 'email', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $customers = $query->get();
        $metrics = $this->partyLedger->customerMetrics($customers);
        $customers->transform(function (Customer $customer) use ($metrics) {
            $metric = $metrics->get($customer->id);
            $customer->setAttribute('security_deposit', $metric['security_deposit']);
            $customer->setAttribute('total_sales', $metric['total_sales']);
            $customer->setAttribute('total_paid', $metric['total_paid']);
            $customer->setAttribute('current_due', $metric['current_due']);

            return $customer;
        });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customers', compact('customers', 'companySetting'));

        return $pdf->stream('customers.pdf');
    }

    public function downloadSalesPdf(Request $request, Customer $customer)
    {
        $customer->load('account');

        $year = $request->get('year', date('Y'));

        $monthlySales = $this->partyLedger->customerMonthlySales(
            $customer->id,
            (int) $year
        );

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customer-sales', compact('customer', 'monthlySales', 'year', 'companySetting'));

        return $pdf->stream('customer-sales.pdf');
    }

    public function downloadPaymentsPdf(Request $request, Customer $customer)
    {
        $customer->load('account');

        $payments = $this->partyLedger->voucherRows(
            $this->partyLedger->vouchers(
                'customer_id',
                $customer->id,
                'receipt',
                $request->start_date,
                $request->end_date
            )
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            'Received'
        );

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customer-payments', compact('customer', 'payments', 'companySetting'));

        return $pdf->stream('customer-payments.pdf');
    }

    public function sendSMS(Request $request, Customer $customer)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'message_type' => 'required|in:template,custom',
            'template_id' => 'required_if:message_type,template|exists:sms_templates,id',
            'custom_message' => 'required_if:message_type,custom|string',
        ]);

        // Get SMS settings
        $smsSetting = SMSSetting::where('status', true)->first();
        if (! $smsSetting) {
            return redirect()->back()->with('error', 'SMS configuration not found.');
        }

        // Prepare message
        $message = '';
        $templateId = null;

        if ($request->message_type === 'template') {
            $template = SMSTemplate::find($request->template_id);
            if (! $template) {
                return redirect()->back()->with('error', 'SMS template not found.');
            }
            $message = $template->message;
            $templateId = $template->id;
        } else {
            $message = $request->custom_message;
        }

        // Replace variables with actual data
        $message = $this->replaceVariables($message, $customer);

        // Send SMS
        $response = $this->sendSMSAPI($smsSetting, $request->phone_number, $message);

        // Log SMS
        SMSLog::create([
            'phone_number' => $request->phone_number,
            'message' => $message,
            'sms_template_id' => $templateId,
            'sms_setting_id' => $smsSetting->id,
            'status' => $response['success'] ? 'sent' : 'failed',
            'response' => json_encode($response),
            'sent_at' => $response['success'] ? now() : null,
            'error_message' => $response['success'] ? null : $response['message'],
        ]);

        if ($response['success']) {
            return redirect()->back()->with('success', 'SMS sent successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to send SMS: '.$response['message']);
        }
    }

    private function replaceVariables($message, $customer)
    {
        $customer->loadMissing('account');
        $metric = $this->partyLedger
            ->customerMetrics(collect([$customer]))
            ->get($customer->id);

        $variables = [
            '{{customer_name}}' => $customer->name,
            '{{account_number}}' => $customer->account->ac_number ?? 'N/A',
            '{{customer_mobile}}' => $customer->mobile ?? 'N/A',
            '{{customer_email}}' => $customer->email ?? 'N/A',
            '{{security_deposit}}' => number_format($metric['security_deposit']),
            '{{total_cradit}}' => number_format($customer->credit_limit ?? 0),
        ];

        $variables['{{total_payment}}'] = number_format($metric['total_paid']);
        $variables['{{total_due}}'] = number_format($metric['current_due']);

        return str_replace(array_keys($variables), array_values($variables), $message);
    }

    private function deleteCustomer(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->loadMissing(['account', 'vehicles.products']);
            $this->assertCustomerCanBeDeleted($customer);

            foreach ($customer->vehicles as $vehicle) {
                $vehicle->products()->detach();
                $vehicle->delete();
            }

            $account = $customer->account;
            $customer->delete();
            $account?->delete();
        });
    }

    private function assertCustomerCanBeDeleted(Customer $customer): void
    {
        if (
            $customer->journalLines()->exists()
            || $customer->creditSaleAllocations()->exists()
            || $customer->sales()->exists()
            || $customer->openingBalances()->exists()
        ) {
            throw ValidationException::withMessages([
                'customer' => 'This customer has financial records and cannot be deleted.',
            ]);
        }
    }

    private function sendSMSAPI($smsSetting, $phoneNumber, $message)
    {
        $data = [
            'api_key' => $smsSetting->api_key,
            'senderid' => $smsSetting->sender_id,
            'number' => $phoneNumber,
            'message' => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $smsSetting->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: '.$error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'HTTP Error: '.$httpCode];
        }

        // Assuming successful response
        return ['success' => true, 'message' => 'SMS sent successfully', 'response' => $response];
    }
}
