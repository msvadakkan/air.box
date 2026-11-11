<?php
/**
 * Rotana Bot: answers visitors' questions from an answer sheet, a document of
 * questions and answers the admin imports in admin/bot.php.
 *
 * Runs fully offline and never writes answers of its own. A question is
 * matched against the sheet (BM25 ranking with light stemming, synonyms and
 * typo tolerance, in English and Arabic) and the reply is the matching answer
 * exactly as written in the sheet. When nothing matches well enough, the bot
 * says so and suggests close questions instead of guessing.
 *
 * Plain PHP 7.4+ with no extensions beyond the defaults; in particular no
 * mbstring, so all Unicode handling goes through PCRE's /u mode.
 */

/**
 * How much of the question's meaning (IDF-weighted share of its words) the
 * best match must cover. At BOT_SURE_COVERAGE or above the bot answers
 * outright; from BOT_MIN_COVERAGE it answers with a "this might be it" note;
 * below that it says it doesn't know and suggests close questions.
 */
const BOT_MIN_COVERAGE = 0.4;
const BOT_SURE_COVERAGE = 0.6;

function bot_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('bot');
    return $db;
}

function bot_settings_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('bot_settings');
    return $db;
}

/** Bot settings with defaults filled in. Empty welcome/fallback = automatic text. */
function bot_settings(): array
{
    $defaults = [
        'name' => 'Rotana Bot',
        'topic' => 'Rotana Jet',
        'welcome' => '',
        'fallback' => '',
        'last_import' => null,
    ];
    $doc = bot_settings_db()->find('settings') ?? [];
    $s = array_merge($defaults, array_intersect_key($doc, $defaults));
    if (trim($s['name']) === '') $s['name'] = $defaults['name'];
    if (trim($s['topic']) === '') $s['topic'] = $defaults['topic'];
    return $s;
}

function bot_save_settings(array $fields): void
{
    if (!bot_settings_db()->update('settings', $fields)) {
        bot_settings_db()->insert(array_merge(['id' => 'settings'], $fields));
    }
}

function bot_entries(): array
{
    return bot_db()->all();
}

function bot_is_ready(): bool
{
    return count(bot_entries()) > 0;
}

/** First $max characters of $text (UTF-8 safe), with an ellipsis if cut. */
function bot_excerpt(string $text, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (bot_char_length($text) <= $max) return $text;
    preg_match('/^.{0,' . $max . '}/su', $text, $m);
    return rtrim($m[0]) . '…';
}

// ------------------------------------------------------------------ text --

/** Drops invalid UTF-8 so PCRE /u functions never fail on odd input. */
function bot_clean_utf8(string $s): string
{
    if (preg_match('//u', $s)) return $s;
    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    return $clean === false ? '' : $clean;
}

/** Lowercase, unify Arabic letter variants, drop diacritics and apostrophes. */
function bot_normalize(string $text): string
{
    $t = bot_clean_utf8($text);
    $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
    $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $t);
    $t = strtr($t, [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        'ی' => 'ي', 'ھ' => 'ه', 'ک' => 'ك', // Persian-style letters some PDFs produce for Arabic text
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    return preg_replace("/['’`]/u", '', $t);
}

function bot_char_length(string $s): int
{
    return (int)preg_match_all('/./su', $s);
}

function bot_stopwords(): array
{
    static $set = null;
    if ($set !== null) return $set;
    $words = 'a an the is are am was were be been being to of in on at by for with from into onto and or but if so as than then '
        . 'i me my mine you your yours we us our ours it its they them their he she his her this that these those there here '
        . 'do does did done doing can could would should will shall may might must have has had having '
        . 'please pls kindly tell know want like let get got any some also just about regarding info information '
        . 'im ive id dont isnt arent cant what which hi hello hey dear sir madam thanks thank fi '
        // everyday filler that says nothing about the topic
        . 'many much take make need needs get go use way able possible type types kind kinds sort '
        . 'bring brought allow allowed permit permitted available offer offers offered provide provides provided '
        . 'show explain describe see give picture pictures photo photos image images work works working '
        . 'في من على الى عن مع هل ما ماذا هو هي هذا هذه ذلك تلك التي الذي الذين ان او ثم كل لا لم لن قد كان كانت يكون '
        . 'انا انت انتم نحن هم لي لك لكم لنا عند ايضا يا ممكن اريد ارجو و '
        . 'يمكن يمكنني يمكننا لديكم لدي لديك عندكم عندك يوجد هناك انواع نوع';
    $set = array_fill_keys(explode(' ', $words), true);
    return $set;
}

/**
 * Everyday words that mean the same thing as the sheet's wording, keyed by
 * stem so "الطائرة" and "طائرات" both reach the same group. Words whose stem
 * collides with an unrelated word are left out on purpose ("fares" stems to
 * "far", "طيارة" also means pilot, "started" isn't only about founding).
 */
function bot_synonyms(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $groups = [
        'aircraft' => 'plane planes airplane airplanes aeroplane aeroplanes aircraft aircrafts fleet طائرة طائرات',
        'baggage' => 'luggage baggage bag bags suitcase suitcases أمتعة حقيبة حقائب شنطة',
        'price' => 'price prices pricing cost costs rate rates fee fees charge charges expensive cheap سعر أسعار تكلفة',
        'book' => 'book booking bookings reserve reservation reservations charter chartering حجز',
        'food' => 'food meal meals catering menu eat eating drink drinks beverage beverages snack snacks طعام وجبة وجبات أكل',
        'internet' => 'internet wifi wi connectivity online إنترنت انترنت واي فاي وايفاي',
        'toilet' => 'toilet toilets lavatory lavatories restroom restrooms bathroom bathrooms washroom wc',
        'child' => 'child children kid kids infant infants baby babies minor minors طفل أطفال',
        'pet' => 'pet pets dog dogs cat cats animal animals',
        'contact' => 'contact phone telephone call email mail reach',
        'passenger' => 'passenger passengers people person persons pax guest guests capacity seat seats ركاب راكب',
        'location' => 'located location based headquarters headquartered hq office offices address situated مقر موقع',
        'founded' => 'founded established founding تأسست تأسيس',
        'cancel' => 'cancel cancellation cancelled canceled refund refunds',
        'smoke' => 'smoke smoking cigarette cigarettes vape vaping',
        'safety' => 'safety safe secure security',
        'advance' => 'advance early ahead beforehand',
        'dimension' => 'dimension dimensions size sizes big large length wingspan width wide height tall',
        'time' => 'time when timing',
    ];
    $map = [];
    foreach ($groups as $canonical => $words) {
        foreach (explode(' ', $words) as $w) {
            $map[bot_stem(bot_normalize($w))] = bot_stem($canonical);
        }
    }
    return $map;
}

function bot_stem(string $w): string
{
    if (preg_match('/^[a-z]+$/', $w)) return bot_stem_en($w);
    if (preg_match('/^\p{Arabic}+$/u', $w)) return bot_stem_ar($w);
    return $w;
}

/** Light English stemmer: enough to match booking/booked/book and bag/bags. */
function bot_stem_en(string $w): string
{
    $n = strlen($w);
    if ($n <= 3) return $w;
    if ($n > 4 && substr($w, -3) === 'ies') $w = substr($w, 0, -3) . 'y';
    elseif (substr($w, -4) === 'sses') $w = substr($w, 0, -2);
    elseif ($n > 5 && substr($w, -3) === 'ing') $w = substr($w, 0, -3);
    elseif ($n > 4 && substr($w, -2) === 'ed') $w = substr($w, 0, -2);
    elseif ($n > 4 && substr($w, -2) === 'ly') $w = substr($w, 0, -2);
    elseif (substr($w, -1) === 's' && !preg_match('/(ss|us|is)$/', $w)) $w = substr($w, 0, -1);
    if (strlen($w) > 3 && preg_match('/([bdglmnprt])\1$/', $w)) $w = substr($w, 0, -1);
    if (strlen($w) > 3 && substr($w, -1) === 'e') $w = substr($w, 0, -1);
    return $w;
}

/** Light Arabic stemmer: strips the article/prefixes and common suffixes. */
function bot_stem_ar(string $w): string
{
    foreach (['وال', 'بال', 'كال', 'فال', 'لل', 'ال'] as $p) {
        if (strpos($w, $p) === 0 && bot_char_length($w) - bot_char_length($p) >= 2) {
            $w = substr($w, strlen($p));
            break;
        }
    }
    foreach (['ات', 'ون', 'ين', 'ان', 'ها', 'هم', 'كم', 'نا', 'يه', 'ه', 'ي'] as $s) {
        if (substr($w, -strlen($s)) === $s && bot_char_length($w) - bot_char_length($s) >= 3) {
            $w = substr($w, 0, -strlen($s));
            break;
        }
    }
    return $w;
}

/** Text -> search terms. */
function bot_tokens(string $text): array
{
    $stop = bot_stopwords();
    $syn = bot_synonyms();
    $out = [];
    foreach (preg_split('/[^\p{L}\p{N}]+/u', bot_normalize($text), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (isset($stop[$w])) continue;
        $w = bot_stem($w);
        if (isset($syn[$w])) $w = $syn[$w];
        if (strlen($w) < 2 && !ctype_digit($w)) continue;
        $out[] = (string)$w;
    }
    return $out;
}

// --------------------------------------------------------------- matching --

function bot_index(array $entries): array
{
    $docs = [];
    $df = [];
    $sumQ = 0;
    $sumA = 0;
    foreach ($entries as $i => $e) {
        $q = array_count_values(bot_tokens($e['question'] ?? ''));
        // A topic that's just a title (its answer repeats it) shouldn't count its words twice.
        $a = ($e['answer'] ?? '') === ($e['question'] ?? '') ? [] : array_count_values(bot_tokens($e['answer'] ?? ''));
        $docs[$i] = ['q' => $q, 'a' => $a, 'ql' => array_sum($q), 'al' => array_sum($a)];
        foreach (array_keys($q + $a) as $t) {
            $df[$t] = ($df[$t] ?? 0) + 1;
        }
        $sumQ += $docs[$i]['ql'];
        $sumA += $docs[$i]['al'];
    }
    $n = max(1, count($entries));
    return ['docs' => $docs, 'df' => $df, 'n' => count($entries), 'avgq' => max(1, $sumQ / $n), 'avga' => max(1, $sumA / $n)];
}

function bot_idf(array $index, $term): float
{
    $df = $index['df'][$term] ?? 0;
    return log(1 + ($index['n'] - $df + 0.5) / ($df + 0.5));
}

/**
 * Query terms with weights. Words the sheet never uses are corrected to a
 * close sheet word when there is one (typos), otherwise kept so they count
 * against the match: a question about something the sheet doesn't cover
 * should get "I don't know", not the nearest unrelated answer.
 */
function bot_query_terms(string $question, array $index): array
{
    // Question words help match an FAQ ("where" vs "when"), but a document
    // that never uses them shouldn't count them against a match.
    static $questionWords = null;
    if ($questionWords === null) {
        $questionWords = array_fill_keys(array_map('bot_stem', ['how', 'where', 'when', 'who', 'why', 'كيف', 'اين', 'متى', 'لماذا']), true);
    }
    $terms = [];
    foreach (bot_tokens($question) as $t) {
        $weight = 1.0;
        if (!isset($index['df'][$t]) && isset($questionWords[$t])) continue;
        // Short words are too easily "corrected" into a different word (serv -> servo).
        if (!isset($index['df'][$t]) && preg_match('/^[a-z]{5,}$/', $t)) {
            $maxDist = strlen($t) >= 7 ? 2 : 1;
            $best = null;
            $bestDist = $maxDist + 1;
            foreach ($index['df'] as $v => $df) {
                $v = (string)$v;
                if (abs(strlen($v) - strlen($t)) > $maxDist || !preg_match('/^[a-z]+$/', $v)) continue;
                $d = levenshtein($t, $v);
                if ($d < $bestDist || ($d === $bestDist && $best !== null && $df > $index['df'][$best])) {
                    $best = $v;
                    $bestDist = $d;
                }
            }
            if ($best !== null && $bestDist <= $maxDist) {
                $t = $best;
                $weight = 0.8;
            }
        }
        $terms[$t] = max($terms[$t] ?? 0, $weight);
    }
    return $terms;
}

/** Ranks every entry against the question. Returns [[index, score, coverage], ...] best first. */
function bot_rank(string $question, array $entries): array
{
    if (!$entries) return [];
    $index = bot_index($entries);
    $terms = bot_query_terms($question, $index);
    if (!$terms) return [];

    $k1 = 1.2;
    $b = 0.75;
    // A word the sheet never uses weighs like a rare sheet word: enough that
    // an off-topic question ("gift cards") isn't matched on its filler words,
    // not so much that one unfamiliar word sinks a clear match.
    $unknown = log(1 + ($index['n'] - 0.5) / 1.5);
    $total = 0.0;
    $idf = [];
    foreach ($terms as $t => $w) {
        $idf[$t] = isset($index['df'][$t]) ? bot_idf($index, $t) : $unknown;
        $total += $idf[$t] * $w;
    }

    $askedInArabic = bot_is_arabic($question);
    $results = [];
    foreach ($index['docs'] as $i => $d) {
        $score = 0.0;
        $covered = 0.0;
        foreach ($terms as $t => $w) {
            $tfq = $d['q'][$t] ?? 0;
            $tfa = $d['a'][$t] ?? 0;
            if (!$tfq && !$tfa) continue;
            $covered += $idf[$t] * $w;
            $sq = $tfq ? $tfq * ($k1 + 1) / ($tfq + $k1 * (1 - $b + $b * $d['ql'] / $index['avgq'])) : 0;
            $sa = $tfa ? $tfa * ($k1 + 1) / ($tfa + $k1 * (1 - $b + $b * $d['al'] / $index['avga'])) : 0;
            $score += $idf[$t] * $w * (2.0 * $sq + $sa);
        }
        if ($score > 0) {
            // In a bilingual sheet, answer in the language the visitor asked in.
            if (bot_is_arabic($entries[$i]['question'] ?? '') === $askedInArabic) $score *= 1.5;
            $results[] = [$i, $score, $total > 0 ? $covered / $total : 0];
        }
    }
    usort($results, function ($x, $y) {
        return $y[1] <=> $x[1] ?: $x[0] <=> $y[0];
    });
    return $results;
}

// ---------------------------------------------------------------- replies --

function bot_is_arabic(string $text): bool
{
    return (bool)preg_match('/\p{Arabic}/u', $text);
}

function bot_welcome(array $s, bool $arabic = false): string
{
    if (trim($s['welcome']) !== '') return $s['welcome'];
    return $arabic
        ? "مرحباً! أنا {$s['name']}. اسألني أي سؤال عن {$s['topic']}."
        : "Hi! I'm {$s['name']}. Ask me anything about {$s['topic']}.";
}

function bot_fallback(array $s, bool $arabic = false): string
{
    if (trim($s['fallback']) !== '') return $s['fallback'];
    return $arabic
        ? 'عذراً، لا أملك إجابة على هذا السؤال. جرّب صياغته بطريقة أخرى، أو اسأل أحد أعضاء فريقنا.'
        : "Sorry, I don't have an answer to that. Try asking another way, or ask a member of our team.";
}

/** Questions to offer as tappable suggestions (sheet order, real questions only). */
function bot_starter_questions(array $entries, int $limit = 4): array
{
    $out = [];
    foreach ($entries as $e) {
        if (($e['kind'] ?? 'qa') !== 'qa' || in_array($e['question'], $out, true)) continue;
        $out[] = $e['question'];
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** Greeting, thanks, goodbye, "who are you". Returns a reply, or null for a real question. */
function bot_small_talk(string $question, array $s, array $entries): ?array
{
    $ar = bot_is_arabic($question);
    $plain = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', bot_normalize($question))));
    $name = preg_quote(bot_normalize($s['name']), '/');

    if (preg_match('/^(hi+|hello|hey|hai|hiya|good (morning|afternoon|evening|day)|salam|salaam|as ?salamu? ?alaikum|assalamu? ?alaikum|marhaba|ahlan|مرحبا|اهلا|اهلا وسهلا|هلا|السلام عليكم|صباح الخير|مساء الخير)( there| bot| ' . $name . ')?$/u', $plain)) {
        return bot_reply_payload('chat', bot_welcome($s, $ar), null, bot_starter_questions($entries), $ar ? 'يمكنك أن تسألني:' : 'You can ask me:');
    }
    if (preg_match('/^(help|menu|options|start|what can (i|you) ask( you)?|مساعده|ساعدني)$/u', $plain)) {
        return bot_reply_payload('chat', bot_welcome($s, $ar), null, bot_starter_questions($entries, 6), $ar ? 'يمكنك أن تسألني:' : 'You can ask me:');
    }
    if (count(explode(' ', $plain)) <= 6 && preg_match('/^(ok(ay)? )?(thanks?|thank you|thankyou|thx|ty|cheers|shukran|great thanks|شكرا|مشكور|يعطيك العافيه)( .*)?$/u', $plain)) {
        return bot_reply_payload('chat', $ar ? 'عفواً! هل هناك شيء آخر تود معرفته؟' : "You're welcome! Is there anything else you'd like to know?");
    }
    if (preg_match('/^(bye|goodbye|good bye|see you|see ya|مع السلامه|الى اللقاء)( .*)?$/u', $plain)) {
        return bot_reply_payload('chat', $ar ? 'مع السلامة! سعدت بمساعدتك.' : 'Goodbye! It was a pleasure to help.');
    }
    if (preg_match('/^((who|what) are you|whats your name|what is your name|your name|are you (a )?(bot|robot|human|real|person|ai)|من انت|ما اسمك|مين انت)$/u', $plain)) {
        return bot_reply_payload('chat', $ar
            ? "أنا {$s['name']}، مساعد افتراضي لـ {$s['topic']}. اسألني أي سؤال وسأجد لك الإجابة."
            : "I'm {$s['name']}, a virtual assistant for {$s['topic']}. Ask me a question and I'll find the answer for you.");
    }
    return null;
}

function bot_reply_payload(string $type, string $text, ?string $matched = null, array $suggestions = [], string $label = '', array $images = [], bool $confident = true): array
{
    return [
        'type' => $type, 'text' => $text, 'matched' => $matched,
        'images' => array_values(array_map('bot_image_url', $images)),
        'confident' => $confident, // shown in the admin test box only
        'suggestions' => array_values(array_unique($suggestions)), 'suggestions_label' => $label,
    ];
}

/** The bot's answer to one visitor question. */
function bot_reply(string $question): array
{
    $s = bot_settings();
    $entries = bot_entries();
    $question = trim(bot_clean_utf8($question));
    $ar = bot_is_arabic($question);

    if ($question === '') {
        return bot_reply_payload('chat', bot_welcome($s, $ar), null, bot_starter_questions($entries), $ar ? 'يمكنك أن تسألني:' : 'You can ask me:');
    }
    $chat = bot_small_talk($question, $s, $entries);
    if ($chat !== null) return $chat;

    $ranked = bot_rank($question, $entries);
    // The best-scoring entry that covers enough of the question (a high score
    // from one rare word alone, like "locking", isn't a match for "gust lock").
    $pick = null;
    foreach ($ranked as $r) {
        if ($r[2] >= BOT_MIN_COVERAGE) {
            $pick = $r;
            break;
        }
    }
    if ($pick !== null) {
        list($best, $topScore, $coverage) = $pick;
        $match = $entries[$best];
        $related = [];
        foreach (array_slice($ranked, 1) as $r) {
            if (count($related) >= 2) break;
            $q = $entries[$r[0]]['question'];
            if ($r[1] >= 0.6 * $topScore && $r[2] >= BOT_MIN_COVERAGE && ($entries[$r[0]]['kind'] ?? 'qa') === 'qa'
                && $q !== $match['question'] && !in_array($q, $related, true)) {
                $related[] = $q;
            }
        }
        return bot_reply_payload('answer', $match['answer'], $match['question'], $related,
            $ar ? 'أسئلة ذات صلة:' : 'Related questions:', $match['images'] ?? [], $coverage >= BOT_SURE_COVERAGE);
    }

    // Not confident: say so, and offer the closest questions (or some starters).
    $close = [];
    foreach ($ranked as $r) {
        if (count($close) >= 3) break;
        if (($entries[$r[0]]['kind'] ?? 'qa') === 'qa') $close[] = $entries[$r[0]]['question'];
    }
    if ($close) {
        return bot_reply_payload('fallback', bot_fallback($s, $ar), null, $close, $ar ? 'هل تقصد:' : 'Did you mean:');
    }
    return bot_reply_payload('fallback', bot_fallback($s, $ar), null, bot_starter_questions($entries, 3), $ar ? 'يمكنك أن تسألني:' : 'You can ask me:');
}

// ---------------------------------------------------------- answer sheet --

const BOT_Q_WORDS = 'what|who|whom|whose|when|where|why|how|which|can|could|do|does|did|is|are|was|were|will|would|should|shall|may|might|have|has|am';
const BOT_Q_WORDS_AR = 'ما|ماذا|من|متى|أين|اين|لماذا|كيف|هل|كم|أي|اي|ماهي|ماهو';
const BOT_RE_Q_MARK = '/^(?:Q|Ques|Question|س|سؤال|السؤال)\s*\d{0,3}\s*[:.)\-–—]\s*(.*)$/iu';
const BOT_RE_A_MARK = '/^(?:A|Ans|Answer|ج|جواب|الجواب|الاجابة|الإجابة)\s*\d{0,3}\s*[:.)\-–—]\s*(.*)$/iu';
const BOT_RE_LIST = '/^(?:[•●▪◦■□➢►✓✔∙·\-–—*]|\(?\d{1,3}[.)]|\(?[a-z][.)])\s+/iu';

function bot_strip_numbering(string $line): string
{
    return preg_replace('/^(?:\(?\d{1,3}\s*[.)\-–:]|[•●▪◦■□➢►✓✔∙·*])\s*/u', '', $line);
}

function bot_is_question_start(string $line): bool
{
    $s = bot_strip_numbering($line);
    return (bool)(preg_match('/^(?:' . BOT_Q_WORDS . ')\b/i', $s) || preg_match('/^(?:' . BOT_Q_WORDS_AR . ')(?:\s|$)/u', $s));
}

function bot_ends_question(string $line): bool
{
    return (bool)preg_match('/[?؟]["”’)\]]*$/u', $line);
}

function bot_ends_sentence(string $line): bool
{
    return (bool)preg_match('/[.!?؟:;)\]"”]$/u', $line);
}

/** Wrapped PDF lines -> readable text. Keeps list items and paragraphs on their own lines. */
function bot_join_lines(array $lines): string
{
    $out = '';
    $blank = false;
    foreach ($lines as $l) {
        if ($l === '') {
            $blank = true;
            continue;
        }
        if ($out === '') {
            $out = $l;
        } elseif (preg_match(BOT_RE_LIST, $l) || preg_match('/:$/u', $out)) {
            $out .= "\n" . $l; // list items stay together even if spaced out in the PDF
        } elseif ($blank) {
            $out .= "\n\n" . $l;
        } elseif (preg_match('/\p{L}-$/u', $out)) {
            $out .= $l; // word broken at a hyphen across lines
        } else {
            $out .= ' ' . $l;
        }
        $blank = false;
    }
    return trim($out);
}

/** Removes a trailing section heading (short line after a blank, no punctuation) from an answer. */
function bot_drop_trailing_heading(array $lines): array
{
    while ($lines && end($lines) === '') array_pop($lines);
    $n = count($lines);
    if ($n >= 3 && $lines[$n - 2] === '') {
        $last = $lines[$n - 1];
        if (bot_char_length($last) <= 60 && !bot_ends_sentence($last) && !preg_match(BOT_RE_LIST, $last)) {
            array_pop($lines);
            while ($lines && end($lines) === '') array_pop($lines);
        }
    }
    return $lines;
}

// Marker lines in text read from a PDF (written by assets/js/pdf-text.js).
const BOT_RE_PAGE = '/^\{\{page:(\d{1,5})\}\}$/';
const BOT_RE_IMG = '/^\{\{img:(\d{1,4})\}\}$/';
const BOT_HEADING = '{{h}}';
// Left where a notice or disclaimer was removed, so a page that only held one isn't mistaken for a title page.
const BOT_REMOVED = '{{removed}}';

function bot_is_marker(string $line): bool
{
    return $line !== '' && $line[0] === '{' && ($line === BOT_REMOVED || preg_match(BOT_RE_PAGE, $line) || preg_match(BOT_RE_IMG, $line));
}

function bot_is_heading_line(string $line): bool
{
    return strpos($line, BOT_HEADING) === 0;
}

function bot_unheading(string $line): string
{
    return bot_is_heading_line($line) ? trim(substr($line, strlen(BOT_HEADING))) : $line;
}

/** Text lines of the sheet, cleaned: no page numbers, repeated page headers/footers, notices or stray spaces. */
function bot_sheet_lines(string $text): array
{
    $text = bot_clean_utf8($text);
    $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);
    $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);
    $text = preg_replace('/[\t\x{00A0}\x{2000}-\x{200A}\x{202F}\x{3000}]/u', ' ', $text);

    $lines = [];
    foreach (explode("\n", $text) as $l) {
        $l = trim(preg_replace('/ {2,}/', ' ', $l));
        $t = bot_unheading($l);
        if ($t === '' && $l !== '') continue; // empty heading
        if ($t !== '' && preg_match('/^(?:(?:page|صفحة|صفحه)\s*)?\d{1,4}(?:\s*(?:of|\/|من)\s*\d{1,4})?$/iu', $t)) continue;
        if ($t !== '' && preg_match('/^[•●▪◦■□➢►✓✔∙·*\-–—]+$/u', $t)) continue; // a bullet with no text
        $lines[] = $l;
    }

    // Lines repeated on many pages (headers/footers) aren't content.
    $pages = 0;
    $onPages = [];
    $count = [];
    foreach ($lines as $l) {
        if (preg_match(BOT_RE_PAGE, $l)) {
            $pages++;
            continue;
        }
        if ($l === '' || bot_is_marker($l)) continue;
        $t = bot_unheading($l);
        $onPages[$t][$pages] = true;
        $count[$t] = ($count[$t] ?? 0) + 1;
    }
    $lines = array_values(array_filter($lines, function ($l) use ($pages, $onPages, $count) {
        if ($l === '' || bot_is_marker($l)) return true;
        $t = bot_unheading($l);
        if (bot_ends_sentence($t)) return true;
        if ($pages >= 3) return count($onPages[$t]) < 3 || count($onPages[$t]) < $pages * 0.3;
        return $count[$t] < 3 || bot_char_length($t) < 12;
    }));

    $lines = bot_strip_notices($lines);

    // Collapse runs of blank lines.
    $out = [];
    foreach ($lines as $l) {
        if ($l === '' && ($out === [] || end($out) === '')) continue;
        $out[] = $l;
    }
    return $out;
}

/** If $line starts with a CAUTION / WARNING / DANGER label, the text after the label; otherwise null. */
function bot_notice_label(string $line): ?string
{
    $s = preg_replace('/^[•●▪◦■□➢►✓✔∙·*\-–—\s]+/u', '', $line);
    // Manuals write these labels in capitals ("CAUTION", "WARNING:"); in normal
    // case only "Caution:" with a colon counts, so "Warning lights come on…" stays.
    if (preg_match('/^(?:CAUTION|WARNING|DANGER)S?(?!\p{L})\s*[:•\-–—!.]?\s*(.*)$/u', $s, $m)) return $m[1];
    if (preg_match('/^(?:Caution|Warning|Danger)\s*[:!]\s*(.*)$/u', $s, $m)) return $m[1];
    return null;
}

/** Typical disclaimer wording in training material and manuals. */
function bot_is_disclaimer(string $text): bool
{
    return (bool)preg_match('/\b(?:for training (?:purposes )?only|training purposes only|familiari[sz]ation (?:purposes )?only'
        . '|for reference (?:purposes )?only|(?:shall|must|should) not be used (?:to|for) (?:perform|carry out|any)'
        . '|refer to (?:your|the) (?:operator|applicable|current|latest|approved)\b[^.]*\b(?:manuals?|publications?|documentation)'
        . '|proper and updated information|sole responsibility of the user)/iu', $text);
}

/**
 * Removes caution/warning notices and disclaimers, which shouldn't appear in
 * the bot's answers: any item (a list item or paragraph, with its wrapped
 * lines) that starts with a CAUTION / WARNING / DANGER label, the item after
 * a label that stands alone, and items with disclaimer wording ("for training
 * purposes only", "shall not be used to perform any maintenance", "refer to
 * your operator manuals"). Headings that merely mention a warning, such as
 * "Fire Warning indication", are kept.
 */
function bot_strip_notices(array $lines): array
{
    // Split into items. A line continues the item before it when that one
    // was cut mid-sentence (a wrapped line), or when it's more capitals
    // after a notice label (notices are often several capitalised sentences).
    $items = [];
    foreach ($lines as $l) {
        $standalone = $l === '' || bot_is_marker($l) || bot_is_heading_line($l);
        $label = $standalone ? null : bot_notice_label($l);
        $last = count($items) - 1;
        $open = !$standalone && $last >= 0 && !$items[$last]['standalone'];
        $capitals = preg_match('/\p{Lu}/u', $l) && !preg_match('/\p{Ll}/u', $l);
        $continues = false;
        if ($open && $label === null && $items[$last]['notice'] && $capitals) {
            $continues = true; // even when each wrapped line has its own bullet
        } elseif ($open && $label === null && !preg_match(BOT_RE_LIST, $l)) {
            $continues = !preg_match('/[.!?؟]["”’)\]]*$/u', end($items[$last]['lines']));
        }
        if ($continues) {
            $items[$last]['lines'][] = $l;
        } else {
            $items[] = ['lines' => [$l], 'standalone' => $standalone, 'notice' => $label !== null];
        }
    }

    $out = [];
    $dropNext = false;
    $removed = function () use (&$out) {
        if (end($out) !== BOT_REMOVED) $out[] = BOT_REMOVED;
    };
    foreach ($items as $item) {
        $first = $item['lines'][0];
        if ($item['standalone']) {
            if ($first !== '') $dropNext = false; // a notice never runs past a heading, photo or page
            $out[] = $first;
            continue;
        }
        $label = bot_notice_label($first);
        if ($label !== null) {
            // A label on its own ("• CAUTION") introduces the next item.
            $rest = $label . ' ' . implode(' ', array_slice($item['lines'], 1));
            $dropNext = !preg_match('/\p{L}/u', $rest);
            $removed();
            continue;
        }
        if ($dropNext) {
            $dropNext = false;
            $removed();
            continue;
        }
        $text = implode(' ', $item['lines']);
        // Disclaimers, and stray pieces of a safety notice (capitals warning of injury, death or damage).
        if (bot_is_disclaimer($text) || (!preg_match('/\p{Ll}/u', $text) && preg_match('/\b(?:INJURY|DEATH|DAMAGE)\b/u', $text))) {
            $removed();
            continue;
        }
        foreach ($item['lines'] as $l) $out[] = $l;
    }
    return $out;
}

/**
 * Answer sheet or document text -> entries:
 * [['question' => ..., 'answer' => ..., 'kind' => 'qa'|'info', 'images' => [photo refs]], ...].
 *
 * Question-and-answer sheets are recognised by, in order of preference:
 *   1. "Q: ... / A: ..." markers (also Question/Answer, Q1., س/ج)
 *   2. question lines ending in "?" (numbered or not, wrapped over lines, or
 *      "Question? Answer" on one line), each followed by its answer
 * Anything else (manuals, brochures, slide decks) is read as a document:
 * each heading or page becomes a topic, with its text as the answer.
 * Photos ({{img:N}} markers) belong to the question or topic they appear in.
 */
function bot_parse_answer_sheet(string $text): array
{
    $lines = bot_sheet_lines($text);
    $fromPdf = (bool)preg_grep(BOT_RE_PAGE, $lines);
    $qaLines = bot_qa_lines($lines);

    if (count(preg_grep(BOT_RE_Q_MARK, $qaLines)) >= 2) {
        $entries = bot_parse_marked($qaLines);
        if (bot_count_qa($entries) >= 2) return $entries;
    }
    $entries = bot_parse_questions($qaLines);
    $qa = bot_count_qa($entries);
    // A document that happens to contain a few questions is still a document.
    if ($qa >= 2 && (!$fromPdf || ($qa >= 3 && bot_qa_share($entries, $qaLines) >= 0.5))) return $entries;
    return bot_parse_document($lines);
}

/** Lines for the question/answer parsers: without page markers and heading marks (photos stay). */
function bot_qa_lines(array $lines): array
{
    $out = [];
    foreach ($lines as $l) {
        if ($l === BOT_REMOVED || preg_match(BOT_RE_PAGE, $l)) continue;
        $out[] = bot_unheading($l);
    }
    return $out;
}

function bot_count_qa(array $entries): int
{
    return count(array_filter($entries, function ($e) { return $e['kind'] === 'qa'; }));
}

/** Share of the sheet's text that ended up in questions and answers. */
function bot_qa_share(array $entries, array $lines): float
{
    $total = 0;
    foreach ($lines as $l) {
        if (!bot_is_marker($l)) $total += bot_char_length($l);
    }
    $inQa = 0;
    foreach ($entries as $e) {
        if ($e['kind'] === 'qa') $inQa += bot_char_length($e['question']) + bot_char_length($e['answer']);
    }
    return $total ? $inQa / $total : 0.0;
}

function bot_finish_entries(array $intro, array $raw, array $introImages = []): array
{
    $entries = [];
    $introText = bot_join_lines($intro);
    if (bot_char_length($introText) >= 120) {
        $first = trim(strtok($introText, "\n"));
        $title = bot_char_length($first) <= 80 && !bot_ends_sentence($first) ? $first : 'Introduction';
        $body = $title === 'Introduction' ? $introText : trim(substr($introText, strlen($first)));
        if ($body !== '') $entries[] = ['question' => $title, 'answer' => $body, 'kind' => 'info', 'images' => $introImages];
    }
    foreach ($raw as $r) {
        $q = trim(preg_replace('/\s+/u', ' ', bot_strip_numbering(bot_join_lines($r['q']))));
        $a = bot_join_lines(bot_drop_trailing_heading($r['a']));
        if ($q !== '' && $a !== '') $entries[] = ['question' => $q, 'answer' => $a, 'kind' => 'qa', 'images' => $r['img']];
    }
    return $entries;
}

function bot_parse_marked(array $lines): array
{
    $intro = [];
    $introImg = [];
    $raw = [];
    $cur = null;
    $state = 'intro';
    foreach ($lines as $l) {
        if (preg_match(BOT_RE_IMG, $l, $m)) {
            if ($cur) $cur['img'][] = (int)$m[1];
            else $introImg[] = (int)$m[1];
            continue;
        }
        if ($l === '') {
            if ($state === 'a') $cur['a'][] = '';
            elseif ($state === 'intro') $intro[] = '';
            continue;
        }
        if (preg_match(BOT_RE_Q_MARK, $l, $m)) {
            if ($cur) $raw[] = $cur;
            $cur = ['q' => [$m[1]], 'a' => [], 'img' => []];
            $state = 'q';
        } elseif ($state === 'q' && preg_match(BOT_RE_A_MARK, $l, $m)) {
            $cur['a'][] = $m[1];
            $state = 'a';
        } elseif ($state === 'q') {
            // Sheets with "Q:" but no "A:": the answer starts after the "?".
            if (bot_ends_question(end($cur['q']))) {
                $cur['a'][] = $l;
                $state = 'a';
            } else {
                $cur['q'][] = $l;
            }
        } elseif ($state === 'a') {
            $cur['a'][] = $l;
        } else {
            $intro[] = $l;
        }
    }
    if ($cur) $raw[] = $cur;
    return bot_finish_entries($intro, $raw, $introImg);
}

function bot_parse_questions(array $lines): array
{
    $intro = [];
    $introImg = [];
    $raw = [];
    $cur = null;
    $reInline = '/^(.{4,220}?[?؟])\s+(\S.*)$/u';
    foreach ($lines as $l) {
        if (preg_match(BOT_RE_IMG, $l, $m)) {
            if ($cur) $cur['img'][] = (int)$m[1];
            else $introImg[] = (int)$m[1];
            continue;
        }
        if ($l === '') {
            if ($cur) $cur['a'][] = '';
            else $intro[] = '';
            continue;
        }
        if (bot_is_question_start($l) && preg_match($reInline, $l, $m) && !bot_ends_question($l)) {
            if ($cur) $raw[] = $cur;
            $cur = ['q' => [$m[1]], 'a' => [$m[2]], 'img' => []];
            continue;
        }
        if (bot_ends_question($l)) {
            $q = [$l];
            if (!bot_is_question_start($l)) {
                // A question wrapped over lines: pull its start back from the previous answer.
                $tail = $cur ? $cur['a'] : $intro;
                $pulled = 0;
                while ($tail && $pulled < 3) {
                    $prev = end($tail);
                    if ($prev === '' || bot_ends_sentence($prev)) break;
                    if (preg_match(BOT_RE_LIST, $prev) && !bot_is_question_start($prev)) break;
                    array_unshift($q, array_pop($tail));
                    $pulled++;
                    if (bot_is_question_start($prev)) break;
                }
                if ($cur) $cur['a'] = $tail;
                else $intro = $tail;
            }
            if ($cur) $raw[] = $cur;
            $cur = ['q' => $q, 'a' => [], 'img' => []];
            continue;
        }
        if ($cur) $cur['a'][] = $l;
        else $intro[] = $l;
    }
    if ($cur) $raw[] = $cur;
    if ($raw) $intro = bot_drop_trailing_heading($intro);
    return bot_finish_entries($intro, $raw, $introImg);
}

/** Plain text (no heading marks): a paragraph of one short line without end punctuation is a heading. */
function bot_mark_headings(array $lines): array
{
    $out = $lines;
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $l = $lines[$i];
        if ($l === '' || bot_is_marker($l)) continue;
        $alone = ($i === 0 || $lines[$i - 1] === '' || bot_is_marker($lines[$i - 1]))
            && ($i === $n - 1 || $lines[$i + 1] === '' || bot_is_marker($lines[$i + 1]));
        if ($alone && bot_char_length($l) <= 80 && !bot_ends_sentence($l) && !preg_match(BOT_RE_LIST, $l)) {
            $out[$i] = BOT_HEADING . $l;
        }
    }
    return $out;
}

/**
 * Documents, manuals and slide decks: each heading starts a topic (and, in a
 * PDF, so does each page). The heading is the topic's "question" and the text
 * under it the answer; its photos come with it. A page that's only pictures
 * belongs to the topic before it.
 */
function bot_parse_document(array $lines): array
{
    if (!preg_grep('/^' . preg_quote(BOT_HEADING, '/') . '/', $lines)) $lines = bot_mark_headings($lines);

    $sections = [];
    $cur = null;
    $divider = null; // title of a heading-only page just before (a section divider)
    $flush = function () use (&$sections, &$cur, &$divider) {
        if ($cur === null) return;
        $hasText = array_filter($cur['lines'], function ($l) { return $l !== ''; });
        if ($hasText || $cur['img']) {
            $sections[] = $cur;
            $divider = null;
        } else {
            // A page with only a title is a divider for what follows; one whose text was all notices isn't.
            $divider = $cur['title'] !== null && empty($cur['removed']) ? $cur['title'] : null;
        }
        $cur = null;
    };
    foreach ($lines as $l) {
        if (preg_match(BOT_RE_PAGE, $l)) {
            $flush();
        } elseif ($l === BOT_REMOVED) {
            if ($cur !== null) $cur['removed'] = true;
        } elseif (preg_match(BOT_RE_IMG, $l, $m)) {
            if ($cur === null) $cur = ['title' => null, 'lines' => [], 'img' => [], 'divider' => $divider];
            $cur['img'][] = (int)$m[1];
        } elseif (bot_is_heading_line($l)) {
            $title = trim(bot_strip_numbering(bot_unheading($l)));
            if ($cur !== null && $cur['title'] !== null && !$cur['lines'] && !$cur['img']) {
                $cur['title'] .= ' ' . $title; // a title set over two lines
            } else {
                $flush();
                $cur = ['title' => $title, 'lines' => [], 'img' => [], 'divider' => $divider];
            }
        } elseif ($l !== '' || $cur !== null) {
            if ($cur === null) $cur = ['title' => null, 'lines' => [], 'img' => [], 'divider' => $divider];
            $cur['lines'][] = $l;
        }
    }
    $flush();

    $entries = [];
    $previous = null; // index of the previous topic's first entry
    foreach ($sections as $sec) {
        $body = bot_join_lines($sec['lines']);
        $title = $sec['title'] !== null ? rtrim($sec['title'], " :") : null;
        // A vague title ("Operation") after a divider page reads better with it.
        if ($title !== null && !empty($sec['divider']) && count(preg_split('/\s+/u', $title)) <= 2) {
            $title = $sec['divider'] . ' – ' . $title;
        }
        if ($title === null && $body === '') {
            // Pictures under a title page belong to that title; otherwise to the topic before.
            if (!empty($sec['divider'])) {
                $title = $sec['divider'];
            } elseif ($previous !== null) {
                $entries[$previous]['images'] = array_merge($entries[$previous]['images'], $sec['img']);
                continue;
            } else {
                $title = 'Photos';
            }
        }
        $kind = 'qa';
        if ($title === null) {
            // Untitled text: its first line is the title if it reads like one.
            $first = trim(strtok($body, "\n"));
            $rest = trim(substr($body, strlen($first)));
            if ($rest !== '' && bot_char_length($first) <= 80 && !bot_ends_sentence($first)) {
                $title = $first;
                $body = $rest;
            } else {
                $kind = 'info';
            }
        }
        $previous = count($entries);
        foreach (bot_split_long($body !== '' ? $body : $title) as $i => $chunk) {
            $entries[] = [
                'question' => $title !== null ? $title : bot_first_sentence($chunk),
                'answer' => $chunk,
                'kind' => $kind,
                'images' => $i === 0 ? $sec['img'] : [],
            ];
        }
    }
    return $entries;
}

function bot_first_sentence(string $text): string
{
    $first = preg_split('/(?<=[.!?؟])\s+/u', trim($text), 2)[0];
    $first = trim(preg_replace('/\s+/u', ' ', $first));
    if (bot_char_length($first) > 100) {
        preg_match('/^.{0,97}(?=\s)/su', $first, $m);
        $first = rtrim($m[0] ?? substr($first, 0, 97), " ,;:") . '…';
    }
    return $first;
}

/** Splits very long sections (~900 characters), at line breaks where possible, else between sentences. */
function bot_split_long(string $text, int $max = 900): array
{
    if (bot_char_length($text) <= $max) return [$text];
    $chunks = [];
    $cur = '';
    foreach (explode("\n", $text) as $line) {
        $pieces = bot_char_length($line) > $max ? preg_split('/(?<=[.!?؟])\s+/u', $line) : [$line];
        foreach ($pieces as $i => $p) {
            if ($cur !== '' && bot_char_length($cur) + bot_char_length($p) > $max) {
                $chunks[] = trim($cur);
                $cur = '';
            }
            $cur .= ($cur === '' ? '' : ($i === 0 ? "\n" : ' ')) . $p;
        }
    }
    if (trim($cur) !== '') $chunks[] = trim($cur);
    return $chunks;
}

// ----------------------------------------------------------------- photos --
// Photos from the answer sheet (or added in the admin page) are stored in
// BOT_IMAGE_DIR under random names and served by image.php?type=bot.

/** Photo names are random hex, so a name from a request can't point anywhere else. */
function bot_valid_image_name(string $name): bool
{
    return (bool)preg_match('/^[0-9a-f]{16}\.(?:jpg|png|webp)$/', $name);
}

function bot_image_url(string $name): string
{
    return image_url('bot', $name);
}

/** Saves one uploaded file as a bot photo. Returns its new name, or null if it isn't a JPEG/PNG/WebP image. */
function bot_save_image_upload(string $tmp, int $size): ?string
{
    if ($size <= 0 || $size > MAX_IMAGE_BYTES || !is_uploaded_file($tmp)) return null;
    $info = @getimagesize($tmp);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) return null;
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    return move_uploaded_file($tmp, BOT_IMAGE_DIR . '/' . $name) ? $name : null;
}

/** Files sent in a multi-file field (name="field[]"): [index => [tmp_name, size]]. */
function bot_uploaded_files(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['tmp_name'])) return [];
    $out = [];
    foreach ($f['tmp_name'] as $i => $tmp) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) $out[$i] = [$tmp, (int)$f['size'][$i]];
    }
    return $out;
}

function bot_delete_images(array $names): void
{
    foreach ($names as $name) {
        if (bot_valid_image_name((string)$name)) @unlink(BOT_IMAGE_DIR . '/' . $name);
    }
}

/** Deletes answers together with their photos. Returns how many answers were deleted. */
function bot_delete_entries(array $ids): int
{
    $ids = array_fill_keys(array_map('strval', $ids), true);
    $photos = [];
    foreach (bot_entries() as $e) {
        if (isset($ids[$e['id'] ?? ''])) $photos = array_merge($photos, $e['images'] ?? []);
    }
    $n = bot_db()->deleteMany(array_keys($ids));
    bot_delete_images($photos);
    return $n;
}

/** Removes photo files no answer uses any more (abandoned or replaced imports) once they're an hour old. */
function bot_gc_images(): void
{
    $used = [];
    foreach (bot_entries() as $e) {
        foreach ($e['images'] ?? [] as $name) $used[$name] = true;
    }
    foreach (glob(BOT_IMAGE_DIR . '/*') ?: [] as $path) {
        $name = basename($path);
        if (bot_valid_image_name($name) && !isset($used[$name]) && filemtime($path) < time() - 3600) @unlink($path);
    }
}
