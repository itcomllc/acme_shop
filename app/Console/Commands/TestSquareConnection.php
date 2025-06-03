<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SquareConfigService;

class TestSquareConnection extends Command
{
    protected $signature = 'square:test';
    protected $description = 'Test Square API connection';

    public function handle()
    {
        $this->info('Testing Square API connection...');
        
        $result = SquareConfigService::testConnection();
        
        if ($result['success']) {
            $this->info('✅ ' . $result['message']);
            if (isset($result['locations_count'])) {
                $this->info('📍 Found ' . $result['locations_count'] . ' location(s)');
            }
        } else {
            $this->error('❌ ' . $result['message']);
            if (isset($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->error('   - ' . ($error['detail'] ?? 'Unknown error'));
                }
            }
        }
    }
}