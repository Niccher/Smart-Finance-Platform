<?php

namespace App\Models;

use CodeIgniter\Model;

class NoteModel extends Model
{
    protected $table      = 'tbl_Transaction_Notes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id', 'sms_id', 'trans_id', 'note', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    public function getForSms(int $smsId, int $userId): ?array
    {
        return $this->where('sms_id', $smsId)->where('user_id', $userId)->first();
    }

    public function getForTrans(int $transId, int $userId): ?array
    {
        return $this->where('trans_id', $transId)->where('user_id', $userId)->first();
    }

    public function saveNote(int $userId, int $smsId, string $note): bool
    {
        $existing = $this->where('sms_id', $smsId)->where('user_id', $userId)->first();
        if ($existing) {
            return $this->update($existing['id'], ['note' => $note]);
        }
        return $this->insert([
            'user_id' => $userId,
            'sms_id'  => $smsId,
            'note'    => $note,
        ]) !== false;
    }

    public function deleteNote(int $id, int $userId): bool
    {
        return (bool)$this->where('id', $id)->where('user_id', $userId)->delete();
    }
}
