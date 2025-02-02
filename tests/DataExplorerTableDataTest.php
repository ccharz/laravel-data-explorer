<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer\Tests;

use Ccharz\DataExplorer\DataExplorerTableData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Snapshots\MatchesSnapshots;

class DataExplorerTableDataTest extends TestCase
{
    use MatchesSnapshots;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
        });

        DB::table('users')->insert([
            'name' => 'Test',
            'email' => 'test@test.at',
            'password' => '',
        ]);
    }

    public function test_it_can_fetch_table_structure(): void
    {
        $class_vars = get_object_vars(DataExplorerTableData::structureFromTable('users'));

        $this->assertMatchesJsonSnapshot($class_vars);
    }

    public function test_it_can_fetch_table_data(): void
    {
        $class_vars = get_object_vars(DataExplorerTableData::fromTable('users'));

        $this->assertMatchesJsonSnapshot($class_vars);
    }

    public function test_it_can_fetch_table_overview(): void
    {
        $class_vars = get_object_vars(DataExplorerTableData::overview());

        $this->assertCount(1, $class_vars['rows']);
    }
}
