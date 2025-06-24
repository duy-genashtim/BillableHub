<?php
namespace Database\Seeders;

use App\Models\ConfigurationSetting;
use App\Models\ConfigurationSettingType;
use Illuminate\Database\Seeder;

class ConfigurationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Run the setting types seeder first
        $this->call(ConfigurationSettingTypeSeeder::class);

        // Full-time work week hours
        $this->createSettings('fulltime_hours', [
            ['setting_value' => '35', 'order' => 1],
            // ['setting_value' => '40', 'order' => 2],
        ]);

        // Part-time work week hours
        $this->createSettings('parttime_hours', [
            ['setting_value' => '20', 'order' => 1],
        ]);

        // Manager types
        $this->createSettings('manager_type', [
            ['setting_value' => 'RTL', 'description' => 'Regional Team Lead'],
            ['setting_value' => 'ARTL', 'description' => 'Assistant Regional Team Lead'],
        ]);

        // Work status
        $this->createSettings('work_status', [
            ['setting_value' => 'full-time', 'description' => 'Full Time'],
            ['setting_value' => 'part-time', 'description' => 'Part Time'],
        ]);

        // Timedoctor version
        $this->createSettings('timedoctor_version', [
            ['setting_value' => '1', 'description' => 'V1 - Classic'],
            ['setting_value' => '2', 'description' => 'V2 - New'],
        ]);

        // Report category types
        $this->createSettings('report_category_type', [
            ['setting_value' => 'billable', 'description' => 'Billable'],
            ['setting_value' => 'non-billable', 'description' => 'Non-Billable'],
        ]);
        // IVAs Logs types
        $this->createSettings('iva_logs_type', [
            ['setting_value' => 'note', 'description' => 'Note'],
            ['setting_value' => 'nad', 'description' => 'NAD'],
            ['setting_value' => 'performance', 'description' => 'Performance'],
        ]);
    }

    /**
     * Create settings for a specific type
     */
    private function createSettings(string $typeKey, array $settings): void
    {
        $settingType = ConfigurationSettingType::where('key', $typeKey)->first();

        if (! $settingType) {
            $this->command->error("Setting type with key '$typeKey' not found.");
            return;
        }

        foreach ($settings as $setting) {
            ConfigurationSetting::create([
                'setting_type_id' => $settingType->id,
                'setting_value'   => $setting['setting_value'],
                'description'     => $setting['description'] ?? null,
                'is_active'       => true,
                'added_by'        => 'System Seeder',
                'order'           => $setting['order'] ?? 0,
                'is_system'       => true, // Mark as system settings so they can't be deleted
            ]);
        }
    }
}
