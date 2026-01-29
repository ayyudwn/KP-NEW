<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@mail.com',
            'npp' => 'A11.2022.2022',
            'no_phone' => '081234567890',
            'password' => Hash::make('superadmin'),
            'tanggal_masuk' => '2020-01-01',
            'position' => 'Kepala Laboratorium',
        ]);
        $superAdmin->assignRole('super_admin');
        
        $this->command->info('Users seeded successfully!');
        $this->command->table(
            ['Name', 'Email', 'NPP', 'Role', 'Password'],
            [
                ['Super Administrator', 'superadmin@mail.com', 'A11.2022.2022', 'super_admin', 'superadmin'],
            ]
        );
    }
}
