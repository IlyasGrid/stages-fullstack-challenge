<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HashPlaintextPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:hash-passwords {--dry-run : Show which users would be hashed without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hash plaintext passwords found in the users table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning users table for plaintext passwords...');

        $users = User::all();
        $toHash = [];

        foreach ($users as $user) {
            if (!$this->isHashed($user->password)) {
                $toHash[] = $user;
            }
        }

        if (empty($toHash)) {
            $this->info('No plaintext passwords found.');
            return 0;
        }

        $this->line('Found ' . count($toHash) . ' user(s) with plaintext passwords:');
        foreach ($toHash as $u) {
            $this->line(" - {$u->email}");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No changes were made.');
            return 0;
        }

        if (!$this->confirm('Proceed to hash these passwords?')) {
            $this->info('Aborted. No changes made.');
            return 1;
        }

        $count = 0;
        foreach ($toHash as $u) {
            $u->password = Hash::make($u->password);
            $u->save();
            $count++;
        }

        $this->info("Hashed {$count} password(s).");
        return 0;
    }

    /**
     * Basic detection if a password string is already hashed.
     */
    protected function isHashed($pw)
    {
        if (!is_string($pw) || $pw === '') {
            return false;
        }

        return strpos($pw, '$2y$') === 0 || strpos($pw, '$argon2') === 0 || strpos($pw, '$argon2id') === 0;
    }
}
