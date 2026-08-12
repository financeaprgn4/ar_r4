<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GameController extends Controller
{
    public function check(Request $request)
    {
        $gameName = $request->query('game');

        if (!$gameName) {
            return response()->json([
                'exists' => false,
                'error' => 'Game name is required.'
            ], 400);
        }

        $exists = DB::table('game_session')
            ->where('game_name', $gameName)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_name' => 'required|string',
            'session_name' => 'required|string|unique:game_session,session_name',
            'last_play' => 'required|date',
            'status' => 'required|string',
            'mode' => 'required|in:single,multi'
        ]);

        DB::beginTransaction();
        try {
            // Insert ke game_session
            DB::table('game_session')->insert([
                'game_name' => $validated['game_name'],
                'session_name' => $validated['session_name'],
                'last_play' => $validated['last_play'],
                'status' => $validated['status'],
            ]);

            $players = [['player_name' => 'Kid', 'turn' => 'On', 'icon' => 'Default_kid.png']];
            if ($validated['mode'] === 'multi') {
                $players[] = ['player_name' => 'Parent', 'turn' => 'Off', 'icon' => 'Default_parent.png'];
            }

            foreach ($players as $player) {
                DB::table('game_snake_ladder')->insert([
                    'session_name' => $validated['session_name'],
                    'player_name' => $player['player_name'],
                    'last_play' => $validated['last_play'],
                    'turn' => $player['turn'],
                    'icon' => $player['icon'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $sessions = DB::table('game_session')
            ->orderBy('last_play', 'desc')
            ->get()
            ->map(function ($session) {
                $players = DB::table('game_snake_ladder')
                    ->where('session_name', $session->session_name)
                    ->get();

                $data = [
                    'session_name' => $session->session_name,
                    'mode' => $players->count() > 1 ? 'Multi Player' : 'Single Player',
                ];

                if ($players->count() === 1) {
                    $player = $players->first();
                    $data = array_merge($data, [
                        'player' => $player->player_name,
                        'turn' => $player->turn,
                        'playerIcon' => $player->icon,
                        'position' => $player->position,
                        'benar' => $player->benar,
                        'salah' => $player->salah,
                        'total_question' => $player->benar + $player->salah,
                    ]);
                } else {
                    // Multi player
                    $player1 = $players[0];
                    $player2 = $players[1];

                    $data = array_merge($data, [
                        'player' => $player1->player_name,
                        'playerIcon' => $player1->icon,
                        'playerTurn' => $player1->turn,
                        'position' => $player1->position,
                        'benar' => $player1->benar,
                        'salah' => $player1->salah,
                        'total_question' => $player1->benar + $player1->salah,

                        'player2' => $player2->player_name,
                        'player2Icon' => $player2->icon,
                        'player2Turn' => $player2->turn,
                        'position2' => $player2->position,
                        'benar2' => $player2->benar,
                        'salah2' => $player2->salah,
                        'total_question2' => $player2->benar + $player2->salah,
                    ]);
                }

                return (object) $data;
            });

        return response()->json($sessions);
    }

    public function getSessionDetail(Request $request)
    {
        $sessionName = $request->query('session_name');
        $player = $request->query('player');

        if (!$sessionName || !$player) {
            return response()->json(['error' => 'Parameter session_name dan player wajib diisi'], 400);
        }

        $sessionData = DB::table('game_snake_ladder')
            ->where('session_name', $sessionName)
            ->where('player_name', $player)
            ->first();

        if (!$sessionData) {
            return response()->json(['error' => 'Session tidak ditemukan'], 404);
        }

        return response()->json([
            'position' => $sessionData->position,
            'question' => $sessionData->question,
            'benar'    => $sessionData->benar,
            'salah'    => $sessionData->salah,
            'last_play' => $sessionData->last_play,
        ]);
    }

    public function getBgStyle(Request $request)
    {
        $bgName = $request->query('bg'); // misalnya 'Background-1.png'

        $bg = DB::table('game_bg_question')
            ->where('bg', $bgName)
            ->select('tile_color', 'btn_color')
            ->first();

        return response()->json($bg);
    }

    public function getRandomQuestion(Request $request)
    {
        $sessionGame = $request->query('session_game');
        $player = $request->query('player');
        $playerPosition = (int) $request->query('playerPosition');

        if (!$sessionGame || !$player || !$playerPosition) {
            return response()->json(['error' => 'Parameter session_game, player, dan playerPosition wajib diisi'], 400);
        }

        // Ambil semua ID pertanyaan yang sudah pernah digunakan
        $usedQuestionIds = DB::table('game_snake_ladder_log')
            ->where('game_session', $sessionGame)
            ->where('player', $player)
            ->pluck('question_id')
            ->toArray();

        // Ambil semua ID dari tabel game_question
        $availableIds = DB::table('game_question')->pluck('id')->toArray();
        $unusedIds = array_diff($availableIds, $usedQuestionIds);

        if (empty($unusedIds)) {
            return response()->json(['error' => 'Semua soal sudah digunakan'], 409);
        }

        // Ambil satu ID acak dari yang belum digunakan
        $randomId = $unusedIds[array_rand($unusedIds)];

        // Ambil soal berdasarkan ID
        $question = DB::table('game_question')->where('id', $randomId)->first();

        if (!$question) {
            return response()->json(['error' => 'Soal tidak ditemukan'], 404);
        }

        // Ambil semua pilihan jawaban berdasarkan kode soal
        $answers = DB::table('game_answer')->where('code', $question->code)->get();

        // Simpan ke tabel log dengan menambahkan field question_code
        DB::table('game_snake_ladder_log')->insert([
            'game_session'  => $sessionGame,
            'player'        => $player,
            'position'      => $playerPosition,
            'question_id'   => $randomId,
            'question_code' => $question->code,
            'last_play'     => Carbon::now(),
        ]);

        return response()->json([
            'question' => $question,
            'answers' => $answers,
        ]);
    }

    public function checkAnswer(Request $request)
    {
        $questionCode     = $request->input('question_code');
        $selectedAnswer   = $request->input('selected_answer');
        $sessionName      = $request->input('session_name');
        $player           = $request->input('player');
        $playerPosition   = (int) $request->input('playerPosition');
        $currentDice      = (int) $request->input('currentDice');
        $mode             = $request->input('mode');

        if (!$questionCode || !$selectedAnswer || !$sessionName || !$player || !$playerPosition) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        $answer = DB::table('game_answer')
            ->where('code', $questionCode)
            ->where('choice', $selectedAnswer)
            ->first();

        if (!$answer) {
            return response()->json(['error' => 'Jawaban tidak ditemukan'], 404);
        }

        $isCorrect = strtoupper($answer->value) === 'T';
        $now = Carbon::now();

        // Update log jawaban
        DB::table('game_snake_ladder_log')
            ->where('game_session', $sessionName)
            ->where('player', $player)
            ->where('question_code', $questionCode)
            ->update([
                'answer'    => $answer->value,
                'last_play' => $now,
            ]);

        // Hitung ulang total, benar, salah
        $jumlahSoal = DB::table('game_snake_ladder_log')
            ->where('game_session', $sessionName)
            ->where('player', $player)
            ->count();

        $jumlahBenar = DB::table('game_snake_ladder_log')
            ->where('game_session', $sessionName)
            ->where('player', $player)
            ->where('answer', 'T')
            ->count();

        $jumlahSalah = DB::table('game_snake_ladder_log')
            ->where('game_session', $sessionName)
            ->where('player', $player)
            ->where('answer', 'F')
            ->count();

        // Update data utama (posisi, statistik)
        DB::table('game_snake_ladder')
            ->where('session_name', $sessionName)
            ->where('player_name', $player)
            ->update([
                'position'   => $playerPosition,
                'question'   => $jumlahSoal,
                'benar'      => $jumlahBenar,
                'salah'      => $jumlahSalah,
                'last_play'  => $now,
            ]);

        // Cek perlu ubah turn atau tidak
        if (!($currentDice === 6 && $isCorrect && $mode === 'Multi Player')) {
            // Set player sekarang menjadi Off
            DB::table('game_snake_ladder')
                ->where('session_name', $sessionName)
                ->where('player_name', $player)
                ->update(['turn' => 'Off']);

            // Set player lainnya menjadi On
            DB::table('game_snake_ladder')
                ->where('session_name', $sessionName)
                ->where('player_name', '<>', $player)
                ->update(['turn' => 'On']);
        }

        return response()->json(['correct' => $isCorrect]);
    }

    public function boardSetting(Request $request)
    {
        $lvl = (int) $request->query('lvl', 1);

        $settings = DB::table('game_board_setting')
            ->where('lvl', $lvl)
            ->orderBy('tile')
            ->get();

        return response()->json($settings, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getCharacters(Request $request)
    {
        $characters = DB::table('game_caracter')->get();
        return response()->json($characters, 200, [], JSON_UNESCAPED_UNICODE);
    }

}
