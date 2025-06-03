<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RefreshAvatarsCommand extends Command
{
    protected $signature   = 'avatars:refresh';
    protected $description = 'Clear existing avatars to force refresh on next login';

    public function handle()
    {
        $users = User::whereNotNull('avatar')->get();

        $this->info("Found {$users->count()} users with avatars");

        if ($users->count() > 0) {
            $confirm = $this->confirm('Do you want to clear all existing avatars? Users will get fresh avatars on next login.');

            if ($confirm) {
                User::whereNotNull('avatar')->update(['avatar' => null]);
                $this->info('All avatars cleared. Users will get fresh avatars on next login.');
            } else {
                $this->info('Operation cancelled.');
            }
        } else {
            $this->info('No users with avatars found.');
        }

        return 0;
    }
}