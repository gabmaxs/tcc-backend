<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = [
            "name" => "Joao Marcos",
            "email" => "email@email.com",
            "password" => Hash::make("secret"),
        ];
        
        User::create($user);
    }
}
