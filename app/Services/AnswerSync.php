<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\TranscriptionServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class AnswerSync
{
    /**
     * Null when the phone could not reach the server, so the screen keeps the list it already has.
     *
     * @return array{pending: bool, answers: list<array{id: string, question: string, answer: string, when: string}>}|null
     */
    public function pull(): ?array
    {
        $url = TranscriptionServer::answersUrl();
        $tenant = Setting::tenant();

        if ($url === null || $tenant === null) {
            return ['pending' => false, 'answers' => []];
        }

        try {
            $response = Http::timeout((int) config('stt.timeout', 60))
                ->withHeaders(['X-Tenant' => $tenant])
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException) {
            return null;
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        $answers = [];

        foreach ($body['answers'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = is_string($row['id'] ?? null) ? $row['id'] : '';
            $question = is_string($row['question'] ?? null) ? $row['question'] : '';
            $answer = is_string($row['answer'] ?? null) ? $row['answer'] : '';

            if ($id === '' || $question === '' || $answer === '') {
                continue;
            }

            $answers[] = [
                'id' => $id,
                'question' => $question,
                'answer' => $answer,
                'when' => $this->when($row['asked_at'] ?? null),
            ];
        }

        return [
            'pending' => (bool) ($body['pending'] ?? false),
            'answers' => $answers,
        ];
    }

    private function when(mixed $askedAt): string
    {
        if (! is_string($askedAt) || $askedAt === '') {
            return '';
        }

        try {
            return Carbon::parse($askedAt)->format('M j, H:i');
        } catch (\Throwable) {
            return '';
        }
    }
}
