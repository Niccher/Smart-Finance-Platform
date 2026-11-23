<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds common M-Pesa / Kenyan finance keyword → category corrections
 * into tbl_Category_Rules.
 *
 * The ML classifier uses these rules (via AnalysisCallbackController) to
 * override its own categorisation when a transaction body matches a known
 * keyword. All rows are idempotent — existing keywords are left untouched.
 *
 * Categories must match the strings the dashboard and reports use:
 *   Paybill | Till | Sent to Mobile | Received | Withdrawal | Fuliza |
 *   Airtime | Loan | Savings | Reversal | Bank | Fintech | SACCO |
 *   Insurance | Uncategorised
 */
class CategoryRulesSeeder extends Seeder
{
    private const RULES = [
        // ── Core M-Pesa transaction types ─────────────────────────────────
        ['keyword' => 'paybill',          'correct_category' => 'Paybill'],
        ['keyword' => 'pay bill',         'correct_category' => 'Paybill'],
        ['keyword' => 'lipa na mpesa',    'correct_category' => 'Paybill'],
        ['keyword' => 'buy goods',        'correct_category' => 'Till'],
        ['keyword' => 'till number',      'correct_category' => 'Till'],
        ['keyword' => 'merchant',         'correct_category' => 'Till'],
        ['keyword' => 'sent to',          'correct_category' => 'Sent to Mobile'],
        ['keyword' => 'you have sent',    'correct_category' => 'Sent to Mobile'],
        ['keyword' => 'received',         'correct_category' => 'Received'],
        ['keyword' => 'you have received','correct_category' => 'Received'],
        ['keyword' => 'withdraw',         'correct_category' => 'Withdrawal'],
        ['keyword' => 'withdrawal',       'correct_category' => 'Withdrawal'],
        ['keyword' => 'agent',            'correct_category' => 'Withdrawal'],
        ['keyword' => 'cash out',         'correct_category' => 'Withdrawal'],
        ['keyword' => 'deposit',          'correct_category' => 'Received'],

        // ── Airtime & data ────────────────────────────────────────────────
        ['keyword' => 'airtime',          'correct_category' => 'Airtime'],
        ['keyword' => 'airtim',           'correct_category' => 'Airtime'],  // common truncation
        ['keyword' => 'data bundle',      'correct_category' => 'Airtime'],
        ['keyword' => 'data pack',        'correct_category' => 'Airtime'],
        ['keyword' => 'bonga points',     'correct_category' => 'Airtime'],

        // ── Loans & credit ────────────────────────────────────────────────
        ['keyword' => 'fuliza',           'correct_category' => 'Fuliza'],
        ['keyword' => 'fuliza mpesa',     'correct_category' => 'Fuliza'],
        ['keyword' => 'loan',             'correct_category' => 'Loan'],
        ['keyword' => 'tala',             'correct_category' => 'Loan'],
        ['keyword' => 'branch',           'correct_category' => 'Loan'],
        ['keyword' => 'zenka',            'correct_category' => 'Loan'],
        ['keyword' => 'timiza',           'correct_category' => 'Loan'],
        ['keyword' => 'hustler fund',     'correct_category' => 'Loan'],
        ['keyword' => 'okash',            'correct_category' => 'Loan'],
        ['keyword' => 'creditbee',        'correct_category' => 'Loan'],
        ['keyword' => 'stawi',            'correct_category' => 'Loan'],
        ['keyword' => 'okoa',             'correct_category' => 'Loan'],

        // ── Savings / investments ─────────────────────────────────────────
        ['keyword' => 'mshwari',          'correct_category' => 'Savings'],
        ['keyword' => 'm-shwari',         'correct_category' => 'Savings'],
        ['keyword' => 'lock savings',     'correct_category' => 'Savings'],
        ['keyword' => 'kcb m-pesa',       'correct_category' => 'Savings'],
        ['keyword' => 'goal account',     'correct_category' => 'Savings'],

        // ── Reversals ─────────────────────────────────────────────────────
        ['keyword' => 'reversal',         'correct_category' => 'Reversal'],
        ['keyword' => 'reversed',         'correct_category' => 'Reversal'],
        ['keyword' => 'refund',           'correct_category' => 'Reversal'],

        // ── Banks ─────────────────────────────────────────────────────────
        ['keyword' => 'kcb',              'correct_category' => 'Bank'],
        ['keyword' => 'equity',           'correct_category' => 'Bank'],
        ['keyword' => 'ncba',             'correct_category' => 'Bank'],
        ['keyword' => 'absa',             'correct_category' => 'Bank'],
        ['keyword' => 'coop bank',        'correct_category' => 'Bank'],
        ['keyword' => 'co-op bank',       'correct_category' => 'Bank'],
        ['keyword' => 'family bank',      'correct_category' => 'Bank'],
        ['keyword' => 'dtb',              'correct_category' => 'Bank'],
        ['keyword' => 'stanbic',          'correct_category' => 'Bank'],
        ['keyword' => 'stanchart',        'correct_category' => 'Bank'],
        ['keyword' => 'i&m',              'correct_category' => 'Bank'],
        ['keyword' => 'pesalink',         'correct_category' => 'Bank'],
        ['keyword' => 'rtgs',             'correct_category' => 'Bank'],
        ['keyword' => 'eft',              'correct_category' => 'Bank'],

        // ── Fintech / payments ────────────────────────────────────────────
        ['keyword' => 'pesapal',          'correct_category' => 'Fintech'],
        ['keyword' => 'jenga',            'correct_category' => 'Fintech'],
        ['keyword' => 'globalpay',        'correct_category' => 'Fintech'],
        ['keyword' => 'pesaflow',         'correct_category' => 'Fintech'],

        // ── SACCO ─────────────────────────────────────────────────────────
        ['keyword' => 'sacco',            'correct_category' => 'SACCO'],
        ['keyword' => 'unaitas',          'correct_category' => 'SACCO'],
        ['keyword' => 'stima sacco',      'correct_category' => 'SACCO'],
        ['keyword' => 'mwalimu sacco',    'correct_category' => 'SACCO'],

        // ── Insurance ─────────────────────────────────────────────────────
        ['keyword' => 'insurance',        'correct_category' => 'Insurance'],
        ['keyword' => 'britam',           'correct_category' => 'Insurance'],
        ['keyword' => 'jubilee',          'correct_category' => 'Insurance'],
        ['keyword' => 'cic insurance',    'correct_category' => 'Insurance'],
        ['keyword' => 'apa insurance',    'correct_category' => 'Insurance'],
        ['keyword' => 'premium',          'correct_category' => 'Insurance'],
        ['keyword' => 'nhif',             'correct_category' => 'Insurance'],
        ['keyword' => 'nssf',             'correct_category' => 'Insurance'],

        // ── Government / utilities ────────────────────────────────────────
        ['keyword' => 'kra',              'correct_category' => 'Paybill'],
        ['keyword' => 'ecitizen',         'correct_category' => 'Paybill'],
        ['keyword' => 'kplc',             'correct_category' => 'Paybill'],
        ['keyword' => 'kenya power',      'correct_category' => 'Paybill'],
        ['keyword' => 'nairobi water',    'correct_category' => 'Paybill'],
        ['keyword' => 'dstv',             'correct_category' => 'Paybill'],
        ['keyword' => 'gotv',             'correct_category' => 'Paybill'],
        ['keyword' => 'startimes',        'correct_category' => 'Paybill'],
        ['keyword' => 'zuku',             'correct_category' => 'Paybill'],
        ['keyword' => 'faiba',            'correct_category' => 'Paybill'],
        ['keyword' => 'safaricom home',   'correct_category' => 'Paybill'],

        // ── Catch-alls ────────────────────────────────────────────────────
        ['keyword' => 'transaction cost', 'correct_category' => 'Uncategorised'],
        ['keyword' => 'service charge',   'correct_category' => 'Uncategorised'],
        ['keyword' => 'failed',           'correct_category' => 'Uncategorised'],
        ['keyword' => 'insufficient',     'correct_category' => 'Uncategorised'],
    ];

    public function run(): void
    {
        $db       = \Config\Database::connect();
        $now      = date('Y-m-d H:i:s');
        $inserted = 0;
        $skipped  = 0;

        foreach (self::RULES as $row) {
            $exists = $db->table('tbl_Category_Rules')
                ->where('keyword', $row['keyword'])
                ->get()->getRow();

            if ($exists) {
                $skipped++;
                continue;
            }

            $db->table('tbl_Category_Rules')->insert([
                'user_id'          => null,
                'keyword'          => $row['keyword'],
                'correct_category' => $row['correct_category'],
                'match_type'       => 'exact',
                'rule_source'      => 'system',
                'hit_count'        => 0,
                'created_at'       => $now,
            ]);
            $inserted++;
        }

        echo "CategoryRulesSeeder: inserted {$inserted}, skipped {$skipped}." . PHP_EOL;
    }
}
