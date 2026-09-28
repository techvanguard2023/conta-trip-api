<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_expected_tables()
    {
        $expectedTables = [
            'users',
            'trips',
            'participants',
            'expenses',
            'expense_splits',
            'recurring_expenses',
            'recurring_expense_occurrences',
            'subscriptions',
            'personal_access_tokens',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabela '{$table}' não foi criada pelas migrations.");
        }
    }
}
