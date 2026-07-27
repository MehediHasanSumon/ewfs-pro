<?php

namespace Database\Seeders;

use App\Models\SMSTemplate;
use Illuminate\Database\Seeder;

class SMSTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Welcome Message',
                'type' => 'welcome',
                'message' => 'Welcome {{customer_name}}! Your account {{account_number}} has been created successfully. Contact us: {{customer_mobile}}',
                'status' => true,
            ],
            [
                'title' => 'Payment Confirmation',
                'type' => 'notification',
                'message' => 'Dear {{customer_name}}, your payment of {{total_payment}} has been received. Remaining balance: {{total_due}}. Thank you!',
                'status' => true,
            ],
            [
                'title' => 'Due Payment Reminder',
                'type' => 'reminder',
                'message' => 'Hi {{customer_name}}, you have a pending payment of {{total_due}} for account {{account_number}}. Please pay soon.',
                'status' => true,
            ],
            [
                'title' => 'Credit Limit Alert',
                'type' => 'alert',
                'message' => 'Alert! {{customer_name}}, your credit limit {{total_cradit}} is almost reached. Current due: {{total_due}}',
                'status' => true,
            ],
            [
                'title' => 'Monthly Statement',
                'type' => 'notification',
                'message' => 'Monthly statement for {{customer_name}}: Total Payment: {{total_payment}}, Due: {{total_due}}, Security Deposit: {{security_deposit}}',
                'status' => true,
            ],
            [
                'title' => 'Special Offer',
                'type' => 'promotional',
                'message' => 'Special offer for {{customer_name}}! Get 10% discount on next payment. Account: {{account_number}}. Call: {{customer_mobile}}',
                'status' => true,
            ],
            [
                'title' => 'Account Update',
                'type' => 'notification',
                'message' => 'Dear {{customer_name}}, your account {{account_number}} has been updated. Email: {{customer_email}}. Contact for queries.',
                'status' => true,
            ],
        ];

        foreach ($templates as $template) {
            SMSTemplate::create($template);
        }
    }
}