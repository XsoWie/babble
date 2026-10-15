<?php
// index.php — главная страница игры
require_once __DIR__ . '/db.php';

// Отдаём стартовые рекорды сразу в HTML — быстрее, чем AJAX на старте
$initialRecords = getTopRecords(5);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Babble - Лопай Пузыри!</title>
    <!-- PWA -->
    <link rel="manifest" href="./manifest.webmanifest">
    <meta name="theme-color" content="#4a90e2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Babble">
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="./icons/icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="./icons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./icons/favicon-16.png">
    <link rel="shortcut icon" href="./icons/favicon.ico">
    <link rel="mask-icon" href="./icons/safari-pinned-tab.svg" color="#4a90e2">

    <!-- Apple / iOS -->
    <link rel="apple-touch-icon" sizes="180x180" href="./icons/apple-touch-icon.png">

    <!-- Windows Tiles -->
    <meta name="msapplication-TileColor" content="#4a90e2">
    <meta name="msapplication-config" content="./browserconfig.xml">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
            --card-bg: rgba(255, 255, 255, 0.8);
            --text-color: #2c3e50;
            --accent-color: #ff7675;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            user-select: none;
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        #canvas-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            cursor: pointer;
        }

        .ui-layer {
            position: relative;
            z-index: 2;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 10px 16px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            pointer-events: auto;
            max-width: 850px;
            margin: 0 auto;
            width: 100%;
        }

        .logo {
            font-size: 22px;
            font-weight: 900;
            color: #4a90e2;
            letter-spacing: 2px;
            text-shadow: 2px 2px 0px rgba(255,255,255,1);
            white-space: nowrap;
            flex-shrink: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .score-board {
            font-size: 16px;
            font-weight: bold;
            background: #ffeaa7;
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px dashed #fdcb6e;
            white-space: nowrap;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .score-board .score-icon {
            font-size: 16px;
            line-height: 1;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .btn-end, .btn-music {
            border: none;
            padding: 10px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
            white-space: nowrap;
            pointer-events: auto;
        }

        .btn-end {
            background: var(--accent-color);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 118, 117, 0.4);
            font-size: 18px;
            padding: 8px 14px;
            line-height: 1;
        }

        .btn-end:hover:not(:disabled) { transform: scale(1.05); }
        .btn-end:disabled { opacity: 0.4; cursor: not-allowed; box-shadow: none; }

        .btn-music {
            background: #dfe6e9;
            color: #2d3436;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            font-size: 18px;
            padding: 8px 12px;
            line-height: 1;
        }

        .btn-music:hover { transform: scale(1.05); }
        .btn-music.playing { background: #55efc4; box-shadow: 0 5px 15px rgba(85, 239, 196, 0.5); }
        .btn-music.mode-dim { opacity: 0.5; }

        .main-card {
            background: var(--card-bg);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            padding: 30px;
            width: 90%;
            max-width: 520px;
            margin: auto;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.5);
            pointer-events: auto;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 70vh;
            overflow-y: auto;
        }

        .main-card h2 { font-size: 24px; margin-bottom: 15px; color: #2d3436; }
        .main-card p  { font-size: 16px; line-height: 1.6; margin-bottom: 20px; color: #636e72; }

        .nav-bar {
            display: flex;
            gap: 15px;
            justify-content: center;
            pointer-events: auto;
            margin-bottom: 10px;
        }

        .nav-btn {
            background: var(--card-bg);
            border: none;
            color: var(--text-color);
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .nav-btn:hover { transform: translateY(-3px); background: #fff; }
        .nav-btn.active { background: #4a90e2; color: white; box-shadow: 0 10px 20px rgba(74, 144, 226, 0.3); }

        .btn-action {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(255, 118, 117, 0.4);
            transition: transform 0.2s;
            margin: 5px;
        }

        .btn-action:hover { transform: scale(1.05); }
        .btn-secondary { background: #0984e3; box-shadow: 0 5px 15px rgba(9, 132, 227, 0.4); }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .records-list { list-style: none; margin: 15px 0; text-align: left; }

        .records-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            font-size: 15px;
            transition: transform 0.2s;
        }

        .records-list li:hover { transform: translateX(5px); }
        .records-list li.top-1 { background: linear-gradient(90deg, #ffeaa7, #fdcb6e); font-weight: bold; }
        .records-list li.top-2 { background: linear-gradient(90deg, #dfe6e9, #b2bec3); }
        .records-list li.top-3 { background: linear-gradient(90deg, #fab1a0, #e17055); color: white; }

        .record-name {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .record-score { font-weight: bold; color: #d63031; }
        .top-3 .record-score { color: #2d3436; }
        .record-date { font-size: 11px; opacity: 0.6; margin-left: 5px; }

        .empty-records { text-align: center; padding: 20px; opacity: 0.6; font-style: italic; }

        .name-input {
            width: 100%;
            padding: 12px 18px;
            border-radius: 15px;
            border: 2px solid #4a90e2;
            font-size: 16px;
            margin: 10px 0;
            outline: none;
            text-align: center;
            font-weight: bold;
        }

        .name-input:focus { box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.2); }

        .new-record-banner {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            border-radius: 20px;
            padding: 15px;
            margin-bottom: 15px;
            font-weight: bold;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.03); }
        }

        .milestone-banner {
            background: linear-gradient(135deg, #a29bfe, #6c5ce7);
            color: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 15px;
            font-weight: bold;
            animation: pulse 1.5s ease-in-out infinite;
        }

        .milestone-banner .big-num {
            font-size: 36px;
            display: block;
            margin: 5px 0;
            text-shadow: 2px 2px 0 rgba(0,0,0,0.15);
        }

        .loading { text-align: center; padding: 10px; opacity: 0.7; }

        @media (max-width: 520px) {
            .ui-layer { padding: 10px; }
            header { padding: 8px 12px; border-radius: 25px; gap: 6px; }
            .logo { font-size: 16px; letter-spacing: 1px; }
            .score-board { font-size: 13px; padding: 5px 10px; border-width: 1px; gap: 4px; }
            .score-board .score-icon { font-size: 13px; }
            .header-actions { gap: 5px; }
            .btn-music, .btn-end { font-size: 15px; padding: 6px 9px; }
            .nav-btn { padding: 9px 16px; font-size: 13px; }
            .nav-bar { gap: 8px; }
            .main-card { padding: 20px; }
            .btn-action { padding: 10px 22px; font-size: 14px; }
        }

        @media (max-width: 360px) {
            .logo { font-size: 14px; }
            .btn-end { font-size: 14px; padding: 5px 8px; }
            .nav-btn { padding: 8px 12px; font-size: 12px; }
        }
    </style>
</head>
<body>

    <canvas id="canvas-bubbles"></canvas>

    <div class="ui-layer">
        <header>
            <div class="logo">BABBLE 🫧</div>
            <div class="score-board" title="Лопнуто пузырей">
                <span class="score-icon">🫧</span>
                <span id="score-val">0</span>
            </div>
            <div class="header-actions">
                <button class="btn-music" id="btn-music" onclick="toggleMusic()" title="Включить/выключить музыку">🔇</button>
                <button class="btn-music mode-dim" id="btn-music-mode" onclick="toggleMusicMode()" title="Переключить музыку">😎</button>
                <button class="btn-end" id="btn-end-game" onclick="endGame()" title="Завершить игру">🏁</button>
                <button class="btn-music" id="btn-install" onclick="installPwa()" title="Установить приложение" style="display:none;">⬇️</button>
            </div>
        </header>

        <div id="dynamic-card" class="main-card"></div>

        <nav class="nav-bar">
            <button class="nav-btn active" onclick="changeTab('play', event)">Играть</button>
            <button class="nav-btn" onclick="changeTab('records', event)">Рекорды</button>
            <button class="nav-btn" onclick="changeTab('about', event)">О проекте</button>
        </nav>
    </div>

    <script>
        // ============ ДАННЫЕ С СЕРВЕРА ============
        // Стартовые рекорды отданы PHP-ом, чтобы не ждать AJAX
        window.__INITIAL_RECORDS__ = <?= json_encode($initialRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const API_URL = 'api.php';
        const MAX_RECORDS = 5;

        let records = Array.isArray(window.__INITIAL_RECORDS__) ? window.__INITIAL_RECORDS__.slice() : [];

        // ============ РАБОТА С API ============
        async function apiList() {
            try {
                const res = await fetch(`${API_URL}?action=list&_=${Date.now()}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data && data.success && Array.isArray(data.records)) {
                    records = data.records;
                    return records;
                }
            } catch (e) {
                console.error('Не удалось загрузить рекорды:', e);
            }
            return records;
        }

        async function apiAdd(name, score) {
            const res = await fetch(`${API_URL}?action=add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ name, score }),
            });
            const data = await res.json();
            if (data && data.success) {
                if (Array.isArray(data.records)) records = data.records;
                return data;
            }
            throw new Error((data && data.error) || 'Ошибка сохранения');
        }

        // ============ ФОНОВАЯ МУЗЫКА (Web Audio API) ============
        // Два режима: 'calm' — спокойный, 'fun' — весёлый.
        const MusicEngine = (() => {
            let audioCtx = null;
            let masterGain = null;
            let isPlaying = false;
            let schedulerTimer = null;
            let nextNoteTime = 0;
            let step = 0;
            let mode = 'fun';

            // Ноты
            const N = {
                C3: 130.81, D3: 146.83, E3: 164.81, F3: 174.61, G3: 196.00, A3: 220.00, B3: 246.94,
                C4: 261.63, D4: 293.66, E4: 329.63, F4: 349.23, G4: 392.00, A4: 440.00, B4: 493.88,
                C5: 523.25, D5: 587.33, E5: 659.25, F5: 698.46, G5: 783.99, A5: 880.00,
                C2: 65.41,  D2: 73.42,  E2: 82.41,  F2: 87.31,  G2: 98.00,  A2: 110.00, B2: 123.47,
            };

            // ============ СПОКОЙНЫЙ РЕЖИМ ============
            const CALM_CHORDS = [
                { bass: N.C3, notes: [N.E4, N.G4, N.C4] },
                { bass: N.A2, notes: [N.C4, N.E4, N.A3] },
                { bass: N.F2, notes: [N.A3, N.C4, N.F3] },
                { bass: N.G2, notes: [N.B3, N.D4, N.G3] },
            ];
            const CALM_CHORD_DURATION = 4.0;

            // ============ ВЕСЁЛЫЙ РЕЖИМ (I–V–vi–IV, ~167 BPM) ============
            const FUN_PROGRESSION = [
                // C-dur
                { bass: [N.C3, N.C3, N.G3, N.C3, N.E3, N.C3, N.G3, N.C3],
                  chord: [N.E4, N.G4, N.C5],
                  melody: [N.E5, null, N.G5, null, N.C5, N.E5, null, N.G4] },
                // G-dur
                { bass: [N.G2, N.G2, N.D3, N.G2, N.B3, N.G2, N.D3, N.G2],
                  chord: [N.D4, N.G4, N.B4],
                  melody: [N.D5, null, N.G5, N.B4, N.D5, null, N.G4, N.B4] },
                // a-moll
                { bass: [N.A2, N.A2, N.E3, N.A2, N.C4, N.A2, N.E3, N.A2],
                  chord: [N.C4, N.E4, N.A4],
                  melody: [N.C5, N.E5, null, N.A4, N.C5, null, N.E5, N.A4] },
                // F-dur
                { bass: [N.F2, N.F2, N.C3, N.F2, N.A3, N.F2, N.C3, N.F2],
                  chord: [N.F4, N.A4, N.C5],
                  melody: [N.A4, N.C5, N.F5, null, N.A4, N.C5, N.F5, null] },
            ];
            const FUN_STEP_DURATION = 0.18;

            const LOOKAHEAD = 0.5;

            function ensureContext() {
                if (audioCtx) return;
                const AC = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AC();
                masterGain = audioCtx.createGain();
                masterGain.gain.value = 0.0;
                masterGain.connect(audioCtx.destination);
            }

            // ---------- Общие инструменты ----------
            function playSineBell(freq, time, duration = 3.0, volume = 0.22) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;

                gain.gain.setValueAtTime(0, time);
                gain.gain.linearRampToValueAtTime(volume, time + 0.15);
                gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(time);
                osc.stop(time + duration + 0.1);
            }

            function playSineBass(freq, time, duration = 1.2, volume = 0.35) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = freq;

                gain.gain.setValueAtTime(0, time);
                gain.gain.linearRampToValueAtTime(volume, time + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(time);
                osc.stop(time + duration + 0.1);
            }

            function playBrightBell(freq, time, duration = 1.2, volume = 0.18) {
                const osc = audioCtx.createOscillator();
                const osc2 = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                const g2 = audioCtx.createGain();

                osc.type = 'triangle';
                osc.frequency.value = freq;
                osc2.type = 'sine';
                osc2.frequency.value = freq * 2;
                g2.gain.value = 0.3;

                gain.gain.setValueAtTime(0, time);
                gain.gain.linearRampToValueAtTime(volume, time + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                osc.connect(gain);
                osc2.connect(g2);
                g2.connect(gain);
                gain.connect(masterGain);

                osc.start(time);
                osc2.start(time);
                osc.stop(time + duration + 0.05);
                osc2.stop(time + duration + 0.05);
            }

            function playSquareBass(freq, time, duration = 0.18, volume = 0.28) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                const filter = audioCtx.createBiquadFilter();
                osc.type = 'square';
                osc.frequency.value = freq;

                filter.type = 'lowpass';
                filter.frequency.value = 800;

                gain.gain.setValueAtTime(0, time);
                gain.gain.linearRampToValueAtTime(volume, time + 0.005);
                gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                osc.connect(filter);
                filter.connect(gain);
                gain.connect(masterGain);
                osc.start(time);
                osc.stop(time + duration + 0.05);
            }

            function playPad(freqs, time, duration = 0.9, volume = 0.05) {
                freqs.forEach((freq) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;

                    gain.gain.setValueAtTime(0, time);
                    gain.gain.linearRampToValueAtTime(volume, time + 0.08);
                    gain.gain.exponentialRampToValueAtTime(0.001, time + duration);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(time);
                    osc.stop(time + duration + 0.05);
                });
            }

            function playShaker(time, volume = 0.05) {
                const bufferSize = Math.floor(audioCtx.sampleRate * 0.05);
                const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) {
                    data[i] = (Math.random() * 2 - 1) * (1 - i / bufferSize);
                }

                const source = audioCtx.createBufferSource();
                source.buffer = buffer;

                const filter = audioCtx.createBiquadFilter();
                filter.type = 'highpass';
                filter.frequency.value = 6000;

                const gain = audioCtx.createGain();
                gain.gain.value = volume;

                source.connect(filter);
                filter.connect(gain);
                gain.connect(masterGain);
                source.start(time);
            }

            // ---------- Планирование шагов ----------
            function scheduleCalmChord(chordIndex, time) {
                const chord = CALM_CHORDS[chordIndex];
                playSineBass(chord.bass, time, 1.5);
                chord.notes.forEach((freq, i) => {
                    playSineBell(freq, time + i * 0.4, 3.2 - i * 0.3, 0.22);
                });
            }

            function scheduleFunStep(time) {
                const totalSteps = FUN_PROGRESSION[0].bass.length;
                const chordIndex = Math.floor(step / totalSteps) % FUN_PROGRESSION.length;
                const stepInChord = step % totalSteps;
                const chord = FUN_PROGRESSION[chordIndex];

                playSquareBass(chord.bass[stepInChord], time, FUN_STEP_DURATION * 0.9, 0.28);

                if (stepInChord === 0 || stepInChord === 4) {
                    playPad(chord.chord, time, FUN_STEP_DURATION * 4, 0.05);
                }

                const note = chord.melody[stepInChord];
                if (note !== null && note !== undefined) {
                    playBrightBell(note, time, 0.9, 0.16);
                }

                const shakerVol = (stepInChord % 2 === 1) ? 0.06 : 0.025;
                playShaker(time, shakerVol);

                step++;
            }

            // ---------- Планировщик ----------
            function scheduler() {
                while (nextNoteTime < audioCtx.currentTime + LOOKAHEAD) {
                    if (mode === 'calm') {
                        const chordIndex = Math.floor(nextNoteTime / CALM_CHORD_DURATION) % CALM_CHORDS.length;
                        scheduleCalmChord(chordIndex, nextNoteTime);
                        nextNoteTime += CALM_CHORD_DURATION;
                    } else {
                        scheduleFunStep(nextNoteTime);
                        nextNoteTime += FUN_STEP_DURATION;
                    }
                }
            }

            // ---------- Управление ----------
            function start() {
                ensureContext();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                if (isPlaying) return;

                isPlaying = true;
                step = 0;
                nextNoteTime = audioCtx.currentTime + 0.1;

                masterGain.gain.cancelScheduledValues(audioCtx.currentTime);
                masterGain.gain.setValueAtTime(masterGain.gain.value, audioCtx.currentTime);
                masterGain.gain.linearRampToValueAtTime(0.07, audioCtx.currentTime + 1.0);

                schedulerTimer = setInterval(scheduler, 60);
            }

            function stop() {
                if (!isPlaying || !audioCtx) return;
                isPlaying = false;

                masterGain.gain.cancelScheduledValues(audioCtx.currentTime);
                masterGain.gain.setValueAtTime(masterGain.gain.value, audioCtx.currentTime);
                masterGain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 0.5);

                clearInterval(schedulerTimer);
                schedulerTimer = null;
            }

            function isActive() { return isPlaying; }

            function setMode(newMode) {
                if (newMode !== 'calm' && newMode !== 'fun') return;
                if (mode === newMode) return;
                mode = newMode;

                // Мягкий переход: следующий шаг начнётся в новом режиме,
                // уже запланированные короткие ноты доиграют сами.
                if (isPlaying && audioCtx) {
                    nextNoteTime = audioCtx.currentTime + 0.05;
                    step = 0;
                }
            }

            function getMode() { return mode; }

            return { start, stop, isActive, setMode, getMode };
        })();

        // ============ СОСТОЯНИЕ ИГРЫ ============
        let score = 0;
        let gameActive = true;
        let nextMilestone = 100;
        let musicEnabled = localStorage.getItem('babble_music') !== 'off';
        let savingRecord = false;

        // Восстанавливаем сохранённый режим музыки
        (function initMusicMode() {
            const savedMode = localStorage.getItem('babble_music_mode');
            if (savedMode === 'calm' || savedMode === 'fun') {
                MusicEngine.setMode(savedMode);
            }
        })();

        const card = document.getElementById('dynamic-card');
        const btnEnd = document.getElementById('btn-end-game');
        const btnMusic = document.getElementById('btn-music');

        function updateEndButton() {
            btnEnd.disabled = !gameActive || score === 0;
        }

        function updateMusicButton() {
            if (MusicEngine.isActive()) {
                btnMusic.classList.add('playing');
                btnMusic.textContent = '🎵';
                btnMusic.title = 'Выключить музыку';
            } else {
                btnMusic.classList.remove('playing');
                btnMusic.textContent = '🔇';
                btnMusic.title = 'Включить музыку';
            }
            // Кнопка режима — полупрозрачная, когда музыка выключена
            const btnMode = document.getElementById('btn-music-mode');
            if (btnMode) btnMode.classList.toggle('mode-dim', !MusicEngine.isActive());
        }

        function updateMusicModeButton() {
            const btn = document.getElementById('btn-music-mode');
            if (!btn) return;
            const mode = MusicEngine.getMode();
            if (mode === 'fun') {
                btn.textContent = '😎';
                btn.title = 'Весёлая музыка — нажми для спокойной';
            } else {
                btn.textContent = '😌';
                btn.title = 'Спокойная музыка — нажми для весёлой';
            }
            btn.classList.toggle('mode-dim', !MusicEngine.isActive());
        }

        function toggleMusic() {
            if (MusicEngine.isActive()) {
                MusicEngine.stop();
                musicEnabled = false;
                localStorage.setItem('babble_music', 'off');
            } else {
                MusicEngine.start();
                musicEnabled = true;
                localStorage.setItem('babble_music', 'on');
            }
            updateMusicButton();
        }

        function toggleMusicMode() {
            const newMode = MusicEngine.getMode() === 'fun' ? 'calm' : 'fun';
            MusicEngine.setMode(newMode);
            localStorage.setItem('babble_music_mode', newMode);
            updateMusicModeButton();
        }

        function tryAutostartMusic() {
            if (musicEnabled && !MusicEngine.isActive()) {
                MusicEngine.start();
                updateMusicButton();
                updateMusicModeButton();
            }
        }

        window.addEventListener('pointerdown', tryAutostartMusic, { once: true });
        window.addEventListener('keydown', tryAutostartMusic, { once: true });

        // ============ ЭКРАНЫ ============
        const tabs = {
            play: () => `
                <h2>Добро пожаловать в Babble!</h2>
                <p>Мыльные пузыри летают вокруг. Кликни по пузырю, чтобы лопнуть его со звуком. Если просто навести мышку — он испугается и убежит!</p>
                <p style="font-size:13px; opacity:0.7;">🏆 Текущий рекорд: <b>${
                    records.length > 0
                        ? records[0].score + ' шт (' + escapeHtml(records[0].name) + ')'
                        : 'пока нет'
                }</b></p>
                <button class="btn-action" onclick="closeCard()">Начать лопать</button>
            `,
            records: () => {
                if (records.length === 0) {
                    return `
                        <h2>Таблица лопателей</h2>
                        <div class="empty-records">Пока никто не лопнул ни одного пузыря 😢<br>Станьте первым!</div>
                        <button class="btn-action" onclick="closeCard()">Играть</button>
                    `;
                }
                const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                const items = records.map((r, i) => `
                    <li class="${i < 3 ? 'top-' + (i + 1) : ''}">
                        <span class="record-name">
                            ${medals[i]} ${escapeHtml(r.name)}
                            <span class="record-date">${escapeHtml(r.date || '')}</span>
                        </span>
                        <span class="record-score">${r.score} шт</span>
                    </li>
                `).join('');

                return `
                    <h2>Таблица лопателей</h2>
                    <ul class="records-list">${items}</ul>
                    <button class="btn-action" onclick="closeCard()">Играть</button>
                `;
            },
            about: () => `
                <h2>Описание игры</h2>
                <p>Залипательная лопальня пузыриков<br>Перелопай рекорд чемпиона и стань первым среди первых<br>Да пребудет с тобой мыло</p>
                <p style="font-size:12px; opacity:0.6;">Сейчас ${new Date().getFullYear()} год</p>
                <button class="btn-action" onclick="closeCard()">Понятно</button>
            `
        };

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        function changeTab(tabName, event) {
            const buttons = document.querySelectorAll('.nav-btn');
            buttons.forEach(b => b.classList.remove('active'));
            if (event && event.target) event.target.classList.add('active');

            card.innerHTML = tabs[tabName]();
            card.style.transform = 'scale(1)';
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            updateEndButton();

            // Для вкладки рекордов подтягиваем свежие данные с сервера
            if (tabName === 'records') {
                apiList().then(() => {
                    if (document.querySelector('.nav-btn.active')?.textContent === 'Рекорды') {
                        card.innerHTML = tabs.records();
                    }
                });
            }
        }

        function closeCard() {
            card.style.transform = 'scale(0.8)';
            card.style.opacity = '0';
            card.style.pointerEvents = 'none';
            updateEndButton();
        }

        // ============ ЗАВЕРШЕНИЕ ИГРЫ ============
        function endGame() {
            if (!gameActive || score === 0) return;
            gameActive = false;
            updateEndButton();
            showSaveRecordForm();
        }

        function showSaveRecordForm() {
            card.innerHTML = `
                <h2>🏁 Игра окончена</h2>
                <p>Ваш результат: <b>${score} шт</b></p>
                <h2>Как вас зовут?</h2>
                <input type="text" class="name-input" id="player-name"
                       placeholder="Ваше имя" maxlength="20" autofocus>
                <button class="btn-action" id="btn-save-record">Сохранить</button>
                <button class="btn-action btn-secondary" onclick="skipRecord()">Без имени</button>
            `;
            card.style.transform = 'scale(1)';
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';

            const input = document.getElementById('player-name');
            const btnSave = document.getElementById('btn-save-record');

            const submit = () => {
                if (savingRecord) return;
                savingRecord = true;
                btnSave.disabled = true;
                btnSave.textContent = 'Сохраняем...';
                const name = input ? input.value.trim() : '';
                submitRecord(name);
            };

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') submit();
            });
            btnSave.addEventListener('click', submit);
            setTimeout(() => input.focus(), 100);
        }

        async function submitRecord(name) {
            try {
                const data = await apiAdd(name || 'Аноним', score);
                records = data.records || records;
                showResultScreen();
            } catch (e) {
                alert('Не удалось сохранить рекорд: ' + e.message);
                showResultScreen();
            } finally {
                savingRecord = false;
            }
        }

        function skipRecord() {
            if (savingRecord) return;
            savingRecord = true;
            submitRecord('Аноним');
        }

        function showResultScreen() {
            const idx = records.findIndex(r => r.score === score);
            const place = idx >= 0 ? idx + 1 : 0;
            const placeText = place > 0 ? `Вы на ${place} месте!` : '';

            card.innerHTML = `
                <h2>🏆 Результат сохранён!</h2>
                <p>${placeText}</p>
                <button class="btn-action" onclick="changeTab('records')">Посмотреть таблицу</button>
                <button class="btn-action btn-secondary" onclick="restartGame()">Играть снова</button>
            `;
        }

        function restartGame() {
            score = 0;
            nextMilestone = 100;
            document.getElementById('score-val').innerText = score;
            gameActive = true;
            updateEndButton();
            closeCard();
        }

        // ============ РУБЕЖИ ============
        function showMilestone(milestone) {
            gameActive = false;
            updateEndButton();

            card.innerHTML = `
                <div class="milestone-banner">
                    🎉 Новый рубеж!
                    <span class="big-num">${milestone} пузырей!</span>
                    Отличная работа!
                </div>
                <h2>Что дальше?</h2>
                <p>Можете продолжить игру и побить свой рекорд, либо сохранить текущий результат.</p>
                <button class="btn-action" onclick="continueGame()">Продолжить игру</button>
                <button class="btn-action btn-secondary" onclick="saveMilestoneResult()">Сохранить результат</button>
            `;
            card.style.transform = 'scale(1)';
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        }

        function continueGame() {
            nextMilestone += 100;
            gameActive = true;
            closeCard();
        }

        function saveMilestoneResult() {
            gameActive = false;
            updateEndButton();
            showSaveRecordForm();
        }

        // ============ ЗВУК POP ============
        function playPopSound(radius) {
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                const audioCtx = new AC();
                const osc = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();

                osc.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                osc.type = 'sine';

                let baseFreq = 900 - (radius * 16);
                if (baseFreq < 150) baseFreq = 150;

                osc.frequency.setValueAtTime(baseFreq, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(baseFreq * 0.2, audioCtx.currentTime + 0.08);

                gainNode.gain.setValueAtTime(0.25, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);

                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.08);
            } catch (e) {
                console.log('Аудио заблокировано', e);
            }
        }

        // ============ CANVAS ============
        const canvas = document.getElementById('canvas-bubbles');
        const ctx = canvas.getContext('2d');

        let w = canvas.width = window.innerWidth;
        let h = canvas.height = window.innerHeight;

        window.addEventListener('resize', () => {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        });

        let mouse = { x: -1000, y: -1000 };
        window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; });
        window.addEventListener('mouseleave', () => { mouse.x = -1000; mouse.y = -1000; });

        class Bubble {
            constructor() {
                this.reset();
                this.y = Math.random() * h;
            }

            reset() {
                this.radius = Math.random() * 25 + 15;
                this.x = Math.random() * w;
                this.y = h + this.radius + Math.random() * 100;
                this.vx = (Math.random() - 0.5) * 1.5;
                this.vy = -(Math.random() * 1.5 + 0.5);
                this.hue = Math.random() * 360;
            }

            update() {
                const dx = this.x - mouse.x;
                const dy = this.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < 120) {
                    const force = (120 - dist) / 120;
                    this.vx += (dx / dist) * force * 0.9;
                    this.vy += (dy / dist) * force * 0.9;
                } else {
                    this.vx *= 0.98;
                    if (this.vy > -0.5) this.vy -= 0.05;
                }

                const maxSpeed = 6;
                const speed = Math.sqrt(this.vx * this.vx + this.vy * this.vy);
                if (speed > maxSpeed) {
                    this.vx = (this.vx / speed) * maxSpeed;
                    this.vy = (this.vy / speed) * maxSpeed;
                }

                this.x += this.vx;
                this.y += this.vy;

                if (this.y < -this.radius) this.reset();
                if (this.x - this.radius < 0 || this.x + this.radius > w) this.vx *= -1;
            }

            draw() {
                ctx.save();
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);

                const gradient = ctx.createRadialGradient(
                    this.x - this.radius / 3, this.y - this.radius / 3, this.radius / 10,
                    this.x, this.y, this.radius
                );
                gradient.addColorStop(0, 'rgba(255, 255, 255, 0.6)');
                gradient.addColorStop(0.6, `hsla(${this.hue}, 80%, 70%, 0.15)`);
                gradient.addColorStop(0.9, `hsla(${this.hue + 40}, 90%, 60%, 0.4)`);
                gradient.addColorStop(1, 'rgba(255, 255, 255, 0.1)');

                ctx.fillStyle = gradient;
                ctx.fill();

                ctx.beginPath();
                ctx.arc(this.x - this.radius / 2.5, this.y - this.radius / 2.5, this.radius / 5, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                ctx.fill();
                ctx.restore();
            }
        }

        class Particle {
            constructor(x, y, hue) {
                this.x = x;
                this.y = y;
                this.radius = Math.random() * 3 + 1;
                const angle = Math.random() * Math.PI * 2;
                const speed = Math.random() * 4 + 2;
                this.vx = Math.cos(angle) * speed;
                this.vy = Math.sin(angle) * speed;
                this.alpha = 1;
                this.hue = hue;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;
                this.alpha -= 0.04;
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = `hsla(${this.hue}, 90%, 60%, 1)`;
                ctx.fill();
                ctx.restore();
            }
        }

        const bubbles = Array.from({ length: 35 }, () => new Bubble());
        let particles = [];

        canvas.addEventListener('mousedown', clickHandler);
        canvas.addEventListener('touchstart', (e) => {
            if (e.touches.length > 0) {
                mouse.x = e.touches[0].clientX;
                mouse.y = e.touches[0].clientY;
                clickHandler(e);
            }
        }, { passive: true });

        function clickHandler() {
            if (!gameActive) return;

            const clickX = mouse.x;
            const clickY = mouse.y;

            for (let i = 0; i < bubbles.length; i++) {
                const b = bubbles[i];
                const dx = b.x - clickX;
                const dy = b.y - clickY;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < b.radius) {
                    playPopSound(b.radius);

                    for (let p = 0; p < 12; p++) {
                        particles.push(new Particle(b.x, b.y, b.hue));
                    }

                    score++;
                    document.getElementById('score-val').innerText = score;
                    updateEndButton();

                    b.reset();

                    if (score >= nextMilestone) {
                        showMilestone(nextMilestone);
                    }

                    break;
                }
            }
        }

        function animate() {
            ctx.clearRect(0, 0, w, h);

            bubbles.forEach(bubble => {
                bubble.update();
                bubble.draw();
            });

            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.update();
                if (p.alpha <= 0) {
                    particles.splice(i, 1);
                } else {
                    p.draw();
                }
            }

            requestAnimationFrame(animate);
        }

        animate();

        // ============ СТАРТ ============
        changeTab('play');
        updateEndButton();
        updateMusicButton();
        updateMusicModeButton();

        // ============ PWA: регистрация Service Worker ============
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./service-worker.js', { scope: '/' })
                    .then((reg) => {
                        console.log('[PWA] Service Worker зарегистрирован:', reg.scope);
                    })
                    .catch((err) => {
                        console.warn('[PWA] Не удалось зарегистрировать Service Worker:', err);
                    });
            });
        }

        // Кнопка «Установить приложение» (для Chrome / Edge / Android)
        let deferredInstallPrompt = null;
        const btnInstall = document.getElementById('btn-install');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredInstallPrompt = e;
            if (btnInstall) btnInstall.style.display = 'inline-block';
        });

        window.addEventListener('appinstalled', () => {
            console.log('[PWA] Приложение установлено');
            deferredInstallPrompt = null;
            if (btnInstall) btnInstall.style.display = 'none';
        });

        function installPwa() {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then((choice) => {
                console.log('[PWA] Выбор пользователя:', choice.outcome);
                deferredInstallPrompt = null;
                if (btnInstall) btnInstall.style.display = 'none';
            });
        }
    </script>
</body>
</html>