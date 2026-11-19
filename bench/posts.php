<?php

/**
 * Short-drama streaming app feedback for seeding the lab and benchmarking duplicate detection.
 *
 *   existing — posts already live (seeded into the lab)
 *   queries  — new posts being checked; `duplicate_of` is the existing post they repeat, or null
 *
 * Areas and categories describe which part of the app a post is about.
 * Query tags: `wrong_area` = the member picked a different area than the original; `short` = very little text.
 *
 * @return array{
 *     existing: array<string, array{area: string, category: string, lang: string, title: string, description: string}>,
 *     queries: array<string, array{area: string, category: string, lang: string, title: string, description: string, duplicate_of: ?string, tags?: list<string>}>
 * }
 */
return [
    'existing' => [
        // Video player
        'E01' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Video keeps buffering on mobile data', 'description' => "Episodes stop every few seconds to load when I'm on 4G, but on Wi-Fi it's fine."],
        'E02' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => "Next episode doesn't autoplay", 'description' => 'When an episode ends the app just stops. I have to go back and tap the next episode myself.'],
        'E03' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Black screen but audio plays', 'description' => "Some episodes show a black screen while the sound keeps playing. Restarting the app doesn't help."],
        'E04' => ['area' => 'video_player', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Let me choose video quality', 'description' => 'Please add a 480p / 720p / 1080p option so I can save mobile data.'],
        'E05' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Video tersekat bila skrin dipusing', 'description' => 'Bila saya pusing telefon ke mod landskap, video berhenti dan tidak boleh disambung.'],
        'E06' => ['area' => 'video_player', 'category' => 'new_feature', 'lang' => 'zh', 'title' => '希望增加倍速播放', 'description' => '想要1.5倍和2倍速播放，有些剧的节奏太慢了。'],
        'E07' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Video restarts from the beginning', 'description' => 'If I leave the app and come back, the episode starts again from 0:00 instead of where I stopped.'],
        'E08' => ['area' => 'video_player', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Skip intro button', 'description' => 'Every episode repeats the same 20-second intro. A skip button would be great.'],

        // Coins & payment
        'E09' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'Coins not credited after top-up', 'description' => "I paid RM9.90 for 500 coins through Google Play but my balance didn't change."],
        'E10' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'Charged twice for VIP subscription', 'description' => 'My bank statement shows two payments for the monthly VIP on the same day.'],
        'E11' => ['area' => 'coins_payment', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Episodes cost too many coins', 'description' => 'Unlocking each episode for 60 coins adds up very quickly. Please lower the price or add bundles.'],
        'E12' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Ganjaran daftar masuk harian tidak diterima', 'description' => 'Saya daftar masuk setiap hari tetapi syiling bonus tidak masuk ke akaun saya.'],
        'E13' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'zh', 'title' => '看广告解锁剧集失败', 'description' => '看完整个广告以后，剧集还是锁着的，金币也没有增加。'],
        'E14' => ['area' => 'coins_payment', 'category' => 'new_feature', 'lang' => 'en', 'title' => "Add Touch 'n Go eWallet payment", 'description' => 'Not everyone has a credit card. Please support TNG or DuitNow for buying coins.'],

        // Search
        'E15' => ['area' => 'search', 'category' => 'bug', 'lang' => 'en', 'title' => 'Search finds nothing for Chinese titles', 'description' => "Typing a drama's Chinese name returns no results even though the drama is in the app."],
        'E16' => ['area' => 'search', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Search by actor name', 'description' => 'I want to find all dramas with a certain actress, but search only matches titles.'],
        'E17' => ['area' => 'search', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Carian tidak berfungsi selepas kemas kini', 'description' => 'Sejak versi baharu, carian tidak memaparkan sebarang hasil walaupun saya taip nama drama yang betul.'],
        'E18' => ['area' => 'search', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Filter search by genre', 'description' => 'Let me filter search results by romance, revenge, CEO, historical and so on.'],

        // Recommendations
        'E19' => ['area' => 'recommendations', 'category' => 'bug', 'lang' => 'en', 'title' => 'For You keeps showing dramas I already finished', 'description' => "My recommendations are full of series I've already watched to the end."],
        'E20' => ['area' => 'recommendations', 'category' => 'suggestion', 'lang' => 'en', 'title' => "Add a 'Not interested' option", 'description' => 'I keep getting horror dramas recommended. Let me hide a genre or a show.'],
        'E21' => ['area' => 'recommendations', 'category' => 'suggestion', 'lang' => 'zh', 'title' => '推荐的剧都差不多', 'description' => '首页推荐全是霸道总裁类型，希望能多推荐一些古装剧。'],
        'E22' => ['area' => 'recommendations', 'category' => 'bug', 'lang' => 'en', 'title' => 'Home feed shows the same dramas every day', 'description' => 'The recommendation list never changes, I see the same 10 shows every time I open the app.'],

        // Account & sign-in
        'E23' => ['area' => 'account', 'category' => 'bug', 'lang' => 'en', 'title' => "Can't log in with Google", 'description' => 'Tapping "Sign in with Google" shows a spinner and then nothing happens.'],
        'E24' => ['area' => 'account', 'category' => 'bug', 'lang' => 'en', 'title' => 'OTP code never arrives', 'description' => 'When I sign up with my phone number I never receive the SMS verification code.'],
        'E25' => ['area' => 'account', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Akaun log keluar sendiri', 'description' => 'Setiap kali saya buka aplikasi, saya perlu log masuk semula.'],
        'E26' => ['area' => 'account', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Let me delete my account', 'description' => "I can't find any way to delete my account and data inside the app."],
        'E27' => ['area' => 'account', 'category' => 'bug', 'lang' => 'zh', 'title' => '换手机后金币和记录都没了', 'description' => '我用同一个账号在新手机登录，之前买的金币和观看记录全部消失了。'],
        'E28' => ['area' => 'account', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Sign in with Apple', 'description' => 'Please add Apple sign-in for iPhone users.'],

        // Subtitles & language
        'E29' => ['area' => 'subtitles_language', 'category' => 'bug', 'lang' => 'en', 'title' => 'Subtitles out of sync', 'description' => 'Subtitles show up about two seconds after the actors speak.'],
        'E30' => ['area' => 'subtitles_language', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Add Malay subtitles', 'description' => 'Most dramas only have English and Chinese subtitles. Please add Bahasa Melayu.'],
        'E31' => ['area' => 'subtitles_language', 'category' => 'bug', 'lang' => 'en', 'title' => 'Wrong translation in subtitles', 'description' => "The English subtitles often don't match what's being said, some lines make no sense."],
        'E32' => ['area' => 'subtitles_language', 'category' => 'suggestion', 'lang' => 'ms', 'title' => 'Saiz sari kata terlalu kecil', 'description' => 'Tolong benarkan saya besarkan saiz tulisan sari kata, susah nak baca.'],
        'E33' => ['area' => 'subtitles_language', 'category' => 'bug', 'lang' => 'zh', 'title' => '字幕突然消失', 'description' => '看到一半字幕就不见了，要退出再进去才会出现。'],
        'E34' => ['area' => 'subtitles_language', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Change app language separately from my phone', 'description' => "The app follows my phone's language. I'd like to set it to Chinese on its own."],

        // Other
        'E35' => ['area' => 'other', 'category' => 'bug', 'lang' => 'en', 'title' => 'App crashes on launch', 'description' => 'Since yesterday the app closes immediately after the splash screen.'],
        'E36' => ['area' => 'other', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Add dark mode', 'description' => 'The white screens are too bright when I watch at night.'],
        'E37' => ['area' => 'other', 'category' => 'bug', 'lang' => 'en', 'title' => 'Phone gets hot and battery drains fast', 'description' => 'Watching for 30 minutes uses 25% battery and the phone gets very hot.'],
        'E38' => ['area' => 'other', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Continue watching list', 'description' => "Show the dramas I'm in the middle of on the home screen so I can resume quickly."],
        'E39' => ['area' => 'other', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Notifikasi episod baharu tidak sampai', 'description' => 'Saya ikut drama ini tetapi tidak dapat notifikasi bila episod baharu keluar.'],
        'E40' => ['area' => 'other', 'category' => 'new_feature', 'lang' => 'zh', 'title' => '希望可以下载剧集离线观看', 'description' => '坐飞机的时候没有网络，希望能先下载几集。'],
    ],

    'queries' => [
        // --- Duplicates: reworded, same language ---
        'Q01' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Endless loading on 4G', 'description' => "Shows keep pausing with a spinning circle whenever I'm not on Wi-Fi.", 'duplicate_of' => 'E01'],
        'Q04' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Have to start each episode manually', 'description' => 'After one episode finishes nothing happens. The next one should play automatically.', 'duplicate_of' => 'E02'],
        'Q05' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Lost my place in the episode', 'description' => 'I switched to WhatsApp for a minute and when I came back the video was back at the start.', 'duplicate_of' => 'E07'],
        'Q06' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'Paid but got no coins', 'description' => 'Bought the 500 coin pack, the money was taken but the coins never showed up.', 'duplicate_of' => 'E09'],
        'Q09' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'Ad finished but episode still locked', 'description' => 'I watched the whole advert to unlock the episode and it stayed locked.', 'duplicate_of' => 'E13'],
        'Q10' => ['area' => 'coins_payment', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Unlocking episodes is too expensive', 'description' => 'A full drama costs thousands of coins, way more than it should.', 'duplicate_of' => 'E11'],
        'Q11' => ['area' => 'search', 'category' => 'bug', 'lang' => 'en', 'title' => "Can't search in Chinese", 'description' => 'When I type the title in Chinese characters the search shows no results.', 'duplicate_of' => 'E15'],
        'Q13' => ['area' => 'recommendations', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Stop recommending horror', 'description' => "I hate horror but the home page keeps suggesting it. Let me tell the app I'm not interested.", 'duplicate_of' => 'E20'],
        'Q14' => ['area' => 'recommendations', 'category' => 'bug', 'lang' => 'en', 'title' => "Recommends shows I've already watched", 'description' => 'Half of For You is dramas I completed last week.', 'duplicate_of' => 'E19'],
        'Q15' => ['area' => 'account', 'category' => 'bug', 'lang' => 'en', 'title' => 'No SMS code', 'description' => 'Signing up with my number, the verification text never comes.', 'duplicate_of' => 'E24'],
        'Q17' => ['area' => 'account', 'category' => 'bug', 'lang' => 'en', 'title' => 'Keeps logging me out', 'description' => "Every time I open the app I'm signed out and have to log in again.", 'duplicate_of' => 'E25'],
        'Q18' => ['area' => 'subtitles_language', 'category' => 'bug', 'lang' => 'en', 'title' => 'Captions lag behind the audio', 'description' => 'The text appears late, after the line has already been spoken.', 'duplicate_of' => 'E29'],
        'Q20' => ['area' => 'subtitles_language', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Make subtitles bigger', 'description' => 'The subtitle text is tiny on my phone, please add a size setting.', 'duplicate_of' => 'E32'],
        'Q21' => ['area' => 'other', 'category' => 'bug', 'lang' => 'en', 'title' => 'App closes right after opening', 'description' => 'I see the logo for a second and then the app shuts down.', 'duplicate_of' => 'E35'],

        // --- Duplicates: different language from the original ---
        'Q02' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Video asyik loading guna data', 'description' => 'Bila guna data mudah alih, video berhenti-henti untuk memuatkan.', 'duplicate_of' => 'E01'],
        'Q03' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'zh', 'title' => '只有声音没有画面', 'description' => '有些集数只能听到声音，屏幕是黑的。', 'duplicate_of' => 'E03'],
        'Q07' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'zh', 'title' => '充值成功但金币没到账', 'description' => '我用Google Play付了钱，可是金币余额没有变。', 'duplicate_of' => 'E09'],
        'Q08' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Dicaj dua kali untuk VIP', 'description' => 'Bank saya tolak duit langganan VIP dua kali pada hari yang sama.', 'duplicate_of' => 'E10'],
        'Q12' => ['area' => 'search', 'category' => 'suggestion', 'lang' => 'ms', 'title' => 'Cari drama ikut nama pelakon', 'description' => 'Saya mahu cari semua drama lakonan pelakon kegemaran saya.', 'duplicate_of' => 'E16'],
        'Q16' => ['area' => 'account', 'category' => 'bug', 'lang' => 'zh', 'title' => '谷歌账号无法登录', 'description' => '点击使用Google登录后一直转圈，登录不了。', 'duplicate_of' => 'E23'],
        'Q19' => ['area' => 'subtitles_language', 'category' => 'new_feature', 'lang' => 'zh', 'title' => '希望增加马来文字幕', 'description' => '很多剧没有马来文字幕，希望可以加上。', 'duplicate_of' => 'E30'],
        'Q22' => ['area' => 'other', 'category' => 'suggestion', 'lang' => 'ms', 'title' => 'Mod gelap', 'description' => 'Skrin putih terlalu terang bila menonton waktu malam.', 'duplicate_of' => 'E36'],
        'Q29' => ['area' => 'other', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Download episodes to watch offline', 'description' => 'Let me save a few episodes before a flight with no internet.', 'duplicate_of' => 'E40'],

        // --- Duplicates: member picked a different area than the original ---
        'Q23' => ['area' => 'video_player', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Save episodes for offline viewing', 'description' => "I'm on a plane next week, please let me keep a few episodes on my phone.", 'duplicate_of' => 'E40', 'tags' => ['wrong_area']],
        'Q24' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => "Words on screen don't match the voices", 'description' => 'The subtitle lines come after the actors have finished talking.', 'duplicate_of' => 'E29', 'tags' => ['wrong_area']],
        'Q28' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'Coins and history gone on new phone', 'description' => 'Logged into my account on a new device and all my purchased coins and watch history are missing.', 'duplicate_of' => 'E27', 'tags' => ['wrong_area']],

        // --- Duplicates: very short posts ---
        'Q25' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Black screen', 'description' => 'Only audio works.', 'duplicate_of' => 'E03', 'tags' => ['short']],
        'Q26' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'zh', 'title' => '金币没到账', 'description' => '付款了，没有金币。', 'duplicate_of' => 'E09', 'tags' => ['short']],
        'Q27' => ['area' => 'account', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Tiada kod OTP', 'description' => 'SMS tak sampai.', 'duplicate_of' => 'E24', 'tags' => ['short']],

        // --- Not duplicates: same area, different problem (should NOT be flagged) ---
        'N01' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => 'Video is blurry even on Wi-Fi', 'description' => "Everything looks pixelated, like it's stuck on the lowest quality.", 'duplicate_of' => null],
        'N02' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'en', 'title' => "Fullscreen button doesn't work", 'description' => 'Tapping the fullscreen icon does nothing on my tablet.', 'duplicate_of' => null],
        'N03' => ['area' => 'video_player', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Picture-in-picture mode', 'description' => 'Let me keep watching in a small window while I use other apps.', 'duplicate_of' => null],
        'N04' => ['area' => 'video_player', 'category' => 'bug', 'lang' => 'ms', 'title' => 'Tiada bunyi pada sesetengah episod', 'description' => 'Gambar ada tetapi langsung tiada bunyi pada episod 5 hingga 8.', 'duplicate_of' => null],
        'N05' => ['area' => 'coins_payment', 'category' => 'bug', 'lang' => 'en', 'title' => 'How do I get a refund?', 'description' => 'I bought coins by mistake and want my money back.', 'duplicate_of' => null],
        'N06' => ['area' => 'coins_payment', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'VIP should include all episodes', 'description' => "Even with VIP some episodes still need coins, that's unfair.", 'duplicate_of' => null],
        'N07' => ['area' => 'coins_payment', 'category' => 'suggestion', 'lang' => 'zh', 'title' => '金币会过期吗', 'description' => '想知道充值的金币有没有使用期限。', 'duplicate_of' => null],
        'N08' => ['area' => 'search', 'category' => 'suggestion', 'lang' => 'en', 'title' => "Search history can't be cleared", 'description' => "My old searches stay in the list and there's no delete button.", 'duplicate_of' => null],
        'N09' => ['area' => 'recommendations', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Recommend more Malay dramas', 'description' => "Most suggestions are Chinese dramas, I'd like more local content.", 'duplicate_of' => null],
        'N10' => ['area' => 'account', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Change my username', 'description' => 'I want to change my display name but the field is locked.', 'duplicate_of' => null],
        'N11' => ['area' => 'account', 'category' => 'new_feature', 'lang' => 'ms', 'title' => 'Log masuk dengan Facebook', 'description' => 'Tolong tambah pilihan log masuk menggunakan akaun Facebook.', 'duplicate_of' => null],
        'N12' => ['area' => 'subtitles_language', 'category' => 'suggestion', 'lang' => 'en', 'title' => "Subtitles cover the actors' faces", 'description' => 'Can the subtitles be moved lower? They block the picture.', 'duplicate_of' => null],
        'N13' => ['area' => 'subtitles_language', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Add Thai subtitles', 'description' => 'Please add Thai subtitles for viewers in Thailand.', 'duplicate_of' => null],
        'N14' => ['area' => 'subtitles_language', 'category' => 'new_feature', 'lang' => 'zh', 'title' => '希望字幕可以选择繁体', 'description' => '现在只有简体中文，希望增加繁体中文字幕。', 'duplicate_of' => null],
        'N15' => ['area' => 'other', 'category' => 'bug', 'lang' => 'en', 'title' => 'App takes a lot of storage', 'description' => 'The app is using 3GB on my phone, mostly cache.', 'duplicate_of' => null],
        'N17' => ['area' => 'other', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Notifications are too frequent', 'description' => 'I get five promo notifications a day, please send fewer.', 'duplicate_of' => null],

        // --- Not duplicates: unrelated or vague ---
        'N16' => ['area' => 'other', 'category' => 'new_feature', 'lang' => 'en', 'title' => 'Share an episode to WhatsApp', 'description' => 'Let me send a link to a specific episode to my friends.', 'duplicate_of' => null],
        'N18' => ['area' => 'coins_payment', 'category' => 'suggestion', 'lang' => 'en', 'title' => 'Coins question', 'description' => 'How do coins work?', 'duplicate_of' => null, 'tags' => ['short']],
    ],
];
