<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive migration ensuring all modern columns exist on tbl_Category_Rules
 * without breaking existing live databases.
 */
class AddColumnsToCategoryRules extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('tbl_Category_Rules')) {
            return;
        }

        $existingColumns = $this->db->getFieldNames('tbl_Category_Rules');
        $fieldsToAdd = [];

        if (!in_array('user_id', $existingColumns, true)) {
            $fieldsToAdd['user_id'] = [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
            ];
        }
        if (!in_array('match_type', $existingColumns, true)) {
            $fieldsToAdd['match_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'exact',
                'after'      => 'correct_category',
            ];
        }
        if (!in_array('rule_source', $existingColumns, true)) {
            $fieldsToAdd['rule_source'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'user',
                'after'      => 'match_type',
            ];
        }
        if (!in_array('hit_count', $existingColumns, true)) {
            $fieldsToAdd['hit_count'] = [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'rule_source',
            ];
        }
        if (!in_array('last_matched_at', $existingColumns, true)) {
            $fieldsToAdd['last_matched_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'hit_count',
            ];
        }

        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('tbl_Category_Rules', $fieldsToAdd);
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists('tbl_Category_Rules')) {
            return;
        }

        $existingColumns = $this->db->getFieldNames('tbl_Category_Rules');
        $columnsToDrop = array_intersect(
            ['user_id', 'match_type', 'rule_source', 'hit_count', 'last_matched_at'],
            $existingColumns
        );

        if (!empty($columnsToDrop)) {
            foreach ($columnsToDrop as $col) {
                $this->forge->dropColumn('tbl_Category_Rules', $col);
            }
        }
    }
}
