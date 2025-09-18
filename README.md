# vue

This template should help get you started developing with Vue 3 in Vite.

## Recommended IDE Setup

[VS Code](https://code.visualstudio.com/) + [Volar](https://marketplace.visualstudio.com/items?itemName=johnsoncodehk.volar) (and disable Vetur).

## Type Support for `.vue` Imports in TS

Since TypeScript cannot handle type information for `.vue` imports, they are shimmed to be a generic Vue component type by default. In most cases this is fine if you don't really care about component prop types outside of templates.

However, if you wish to get actual prop types in `.vue` imports (for example to get props validation when using manual `h(...)` calls), you can run `Volar: Switch TS Plugin on/off` from VS Code command palette.

## Customize configuration

See [Vite Configuration Reference](https://vitejs.dev/config/).

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Type-Check, Compile and Minify for Production
sudo chmod -R 755 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache public
sudo chmod -R ug+rwX storage bootstrap/cache

rm /var/www/iva/storage/logs/laravel.log

sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
php artisan storage:link
```sh
npm run build
```
run :
php artisan migrate
    php artisan db:seed
-> login
 php artisan db:seed --class=RolePermissionSeeder
 php artisan db:seed --class=ConfigurationSettingsSeeder
->sync iva
-> sync tasks
 php artisan db:seed --class=RegionSeeder
 php artisan db:seed --class=TaskCategorySeeder
 be notice: Task not found: [Verification work]: Pending B Corp
sudo -u www-data php artisan optimize

-------
sudo -u www-data  php artisan timedoctor:sync-worklogs

sudo -u www-data php artisan timedoctor:sync-worklogs --start-date=2025-08-04 --end-date=2025-08-27

sudo -u www-data php artisan timedoctor:sync-worklogs --start-date=2025-07-21 --end-date=2025-08-03

sudo -u www-data php artisan timedoctor:sync-worklogs --start-date=2025-06-02 --end-date=2025-06-29

sudo -u www-data php artisan timedoctor:sync-worklogs --start-date=2025-06-30 --end-date=2025-07-27
-----------
timedoctor timerecord changes (edit record, add record, modify record.)
-edit record:- 
    - it should be change in the timedoctor - then sync to the system - old data of that date will be remove to resync.

icon:
https://remixicon.com/
-
delete iva_user_customize_iva_user_id_setting_id_unique
