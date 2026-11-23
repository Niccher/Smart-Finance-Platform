<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Squashed baseline migration for tbl_Category_Rules.
 *
 * Source: 2026-08-12-000001_CreateTblCategoryRules (unchanged)
 * Category override rules (keyword -> correct_category).
 */
class CreateTblCategoryRules extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'keyword' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'correct_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'match_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'exact',
            ],
            'rule_source' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'user',
            ],
            'hit_count' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'last_matched_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'keyword']);
        $this->forge->addKey('keyword');
        $this->forge->createTable('tbl_Category_Rules', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('tbl_Category_Rules', true);
    }
}
