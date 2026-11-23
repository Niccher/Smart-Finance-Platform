<?php

namespace App\Controllers\Api\V1;

use App\Models\NoteModel;
use CodeIgniter\HTTP\ResponseInterface;

class NotesController extends BaseApiController
{
    public function noteGet(int $smsId): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $note = (new NoteModel())->getForSms($smsId, (int)$user->id);
        return $this->respond([
            'status' => '1',
            'note' => $note['note'] ?? '',
        ]);
    }

    public function noteSave(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $smsId = (int)$this->request->getPost('sms_id');
        $note = $this->request->getPost('note');

        if ($smsId > 0) {
            (new NoteModel())->saveNote($user->id, $smsId, $note ?? '');
        }

        return $this->respond(['status' => '1', 'message' => 'Note saved']);
    }
}
