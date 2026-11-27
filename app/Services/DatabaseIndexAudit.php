<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseIndexAudit
{
    // Candidate left-prefixes derived from current application queries.
    // These are inspection hints, not instructions to execute DDL.
    private const CANDIDATES = [
        'leerlingen' => [
            ['schoolid','active'], ['schoolid','gebruikersnaam'],
            ['schoolid','InformatInternNr'], ['schoolid','klasid','active'],
        ],
        'users' => [['smartschool_gebruikersnaam','allowed_login'], ['schoolid','smartschool_gebruikersnaam']],
        'meldingen' => [['leerlingId','gebeurdop'], ['meldingssoortId'], ['created_at']],
        'meldingssoorten' => [['meldingscategorieId']],
        'portal_role_permissions' => [['role','permission']],
        'portal_user_permissions' => [['user_id','permission']],
        'portal_student_permissions' => [['leerling_id','permission']],
        'class_staff_links' => [['school_id','school_year','user_id','kind','class_id']],
        'skore_student_teacher_links' => [['school_id','school_year','user_id','student_id','class_id']],
        'lvs_visibility_matrix' => [['school_id','relation']],
        'library_loans' => [['returned_at','due_date']],
    ];

    public function inspect(): array
    {
        $columns = DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
        $indexes = DB::select('SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, COLUMN_NAME AS column_name, SEQ_IN_INDEX AS position, SUB_PART AS sub_part, NON_UNIQUE AS non_unique, INDEX_TYPE AS index_type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX');
        $tables = [];
        foreach ($columns as $column) $tables[$column->table_name][$column->column_name] = true;
        $existing = [];
        foreach ($indexes as $index) {
            $entry = &$existing[$index->table_name][$index->index_name];
            $entry['columns'][] = $index->column_name;
            $entry['parts'][] = $index->column_name.($index->sub_part === null ? '' : ' ('.$index->sub_part.')');
            $entry['type'] = $index->index_type;
            $entry['full'] = ($entry['full'] ?? true) && $index->sub_part === null && strtoupper($index->index_type) === 'BTREE';
            $entry['unique'] = !(bool)$index->non_unique;
            unset($entry);
        }
        $result = [];
        foreach (self::CANDIDATES as $table => $candidates) {
            foreach ($candidates as $candidate) {
                if (array_diff($candidate, array_keys($tables[$table] ?? []))) {
                    $result[] = ['table'=>$table,'columns'=>$candidate,'status'=>'unavailable','index'=>null];
                    continue;
                }
                $match = null;
                foreach ($existing[$table] ?? [] as $name => $index) {
                    if ($index['full'] && array_slice($index['columns'], 0, count($candidate)) === $candidate) {
                        $match = $name;
                        break;
                    }
                }
                $result[] = ['table'=>$table,'columns'=>$candidate,'status'=>$match === null ? 'review' : 'covered','index'=>$match];
            }
        }
        $inventory = [];
        foreach ($existing as $table => $items) {
            foreach ($items as $name => $index) $inventory[] = ['table'=>$table,'name'=>$name,'columns'=>$index['parts'],'unique'=>$index['unique'],'type'=>$index['type']];
        }
        return ['candidates'=>$result,'inventory'=>$inventory];
    }
}
