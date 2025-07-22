<?php
namespace Database\Seeders;

use App\Models\ConfigurationSettingType;
use Illuminate\Database\Seeder;

class ConfigurationSettingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settingTypes = [
            [
                'key'                => 'fulltime_hours',
                'name'               => 'Full-time Work week hours',
                'description'        => 'Standard hours for full-time employees',
                'setting_category'   => 'report-time',
                'for_user_customize' => true,
                'allow_edit'         => true,
                'allow_delete'       => false,
                'allow_create'       => true,
            ],
            [
                'key'                => 'parttime_hours',
                'name'               => 'Part-time Work week hours',
                'description'        => 'Standard hours for part-time employees',
                'setting_category'   => 'report-time',
                'for_user_customize' => true,
                'allow_edit'         => true,
                'allow_delete'       => false,
                'allow_create'       => true,
            ],
            [
                'key'                => 'manager_type',
                'name'               => 'Manager Type',
                'description'        => 'Types of managers for IVAs',
                'setting_category'   => 'user',
                'for_user_customize' => false,
                'allow_edit'         => true,
                'allow_delete'       => false,
                'allow_create'       => true,
            ],
            [
                'key'                => 'tocert_coo_email',
                'name'               => 'ToCert COO Email',
                'description'        => 'Chief Operating Officer for ToCert',
                'setting_category'   => 'user',
                'for_user_customize' => false,
                'allow_edit'         => true,
                'allow_delete'       => false,
                'allow_create'       => false,
            ],
            [
                'key'                => 'work_status',
                'name'               => 'Work Status',
                'description'        => 'Types of work status for IVAs',
                'setting_category'   => 'user',
                'for_user_customize' => false,
                'allow_edit'         => false,
                'allow_delete'       => false,
                'allow_create'       => false,
            ],
            [
                'key'                => 'timedoctor_version',
                'name'               => 'TimeDoctor Version',
                'description'        => 'Version of Time Doctor used in the system',
                'setting_category'   => 'system',
                'for_user_customize' => false,
                'allow_edit'         => false,
                'allow_delete'       => false,
                'allow_create'       => false,
            ],
            [
                'key'                => 'report_category_type',
                'name'               => 'Report Category Type',
                'description'        => 'Types of report categories in the system',
                'setting_category'   => 'report-cat',
                'for_user_customize' => false,
                'allow_edit'         => false,
                'allow_delete'       => false,
                'allow_create'       => false,
            ],
            [
                'key'                => 'iva_logs_type',
                'name'               => 'IVAs Logs Type',
                'description'        => 'Types of logs for IVAs',
                'setting_category'   => 'other',
                'for_user_customize' => false,
                'allow_edit'         => false,
                'allow_delete'       => false,
                'allow_create'       => true,
            ],

        ];

        foreach ($settingTypes as $settingType) {
            ConfigurationSettingType::firstOrCreate(
                ['key' => $settingType['key']],
                $settingType
            );
        }
    }
}
