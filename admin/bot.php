<?php
// Admin page for Rotana Bot: import the answer sheet or document (text and
// photos), test the bot, fix individual answers and their photos, and change
// what the bot is called and says.
require __DIR__ . '/../includes/config.php';
require_admin();

$errors = [];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Photos read from a PDF arrive here in batches before the import itself
    // (assets/js/bot-admin.js). Returns {"saved": {ref: file name}}.
    if ($action === 'upload_images') {
        $refs = (array)($_POST['refs'] ?? []);
        $saved = [];
        foreach (bot_uploaded_files('images') as $i => $file) {
            $name = bot_save_image_upload($file[0], $file[1]);
            if ($name !== null && isset($refs[$i])) $saved[(string)(int)$refs[$i]] = $name;
        }
        header('Content-Type: application/json');
        echo json_encode(['saved' => (object)$saved]);
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $n = bot_delete_entries([(string)$_POST['delete_id']]);
        redirect(base_url('admin/bot.php?deleted=' . $n . '#answers'));
    }
    if (isset($_POST['bulk_delete'])) {
        $n = bot_delete_entries((array)($_POST['ids'] ?? []));
        redirect(base_url('admin/bot.php?deleted=' . $n . '#answers'));
    }

    if ($action === 'import') {
        // The browser reads the PDF and sends its text, plus the names of the
        // photos it already uploaded ({ref: file name}).
        $text = trim((string)($_POST['sheet_text'] ?? ''));
        $source = trim((string)($_POST['sheet_name'] ?? ''));
        if ($text === '') {
            $text = trim((string)($_POST['pasted_text'] ?? ''));
            $source = 'pasted text';
        }
        $photoMap = json_decode((string)($_POST['sheet_images'] ?? ''), true);
        $photoMap = is_array($photoMap) ? $photoMap : [];
        if ($text === '') {
            $errors[] = 'Nothing was received. Choose a PDF, or paste the text.';
        } else {
            $entries = bot_parse_answer_sheet($text);
            if (!$entries) {
                $errors[] = "Couldn't find any questions, answers or topics in that text.";
            } else {
                foreach ($entries as &$e) {
                    $photos = [];
                    foreach ($e['images'] as $ref) {
                        $name = (string)($photoMap[$ref] ?? '');
                        if (bot_valid_image_name($name) && is_file(BOT_IMAGE_DIR . '/' . $name)) $photos[] = $name;
                    }
                    $e['images'] = $photos;
                    $e['source'] = $source;
                }
                unset($e);
                $replace = ($_POST['mode'] ?? 'replace') !== 'append';
                $oldPhotos = [];
                if ($replace) {
                    foreach (bot_entries() as $old) $oldPhotos = array_merge($oldPhotos, $old['images'] ?? []);
                }
                bot_db()->insertMany($entries, $replace);
                bot_delete_images($oldPhotos);
                bot_gc_images();
                bot_save_settings(['last_import' => ['source' => $source, 'at' => date('c'), 'count' => count($entries)]]);
                redirect(base_url('admin/bot.php?imported=' . count($entries) . ($replace ? '' : '&appended=1') . '#answers'));
            }
        }
    }

    if ($action === 'add' || $action === 'update') {
        $question = trim((string)($_POST['question'] ?? ''));
        $answer = trim((string)($_POST['answer'] ?? ''));
        $kind = isset($_POST['suggest']) ? 'qa' : 'info';
        if ($question === '' || $answer === '') {
            $errors[] = 'Both the question and the answer are needed.';
        } else {
            $newPhotos = [];
            foreach (bot_uploaded_files('photos') as $file) {
                $name = bot_save_image_upload($file[0], $file[1]);
                if ($name !== null) $newPhotos[] = $name;
            }
            if ($action === 'add') {
                bot_db()->insert(['question' => $question, 'answer' => $answer, 'kind' => $kind, 'images' => $newPhotos, 'source' => 'added by hand']);
                redirect(base_url('admin/bot.php?added=1#answers'));
            }
            $id = (string)($_POST['id'] ?? '');
            $entry = bot_db()->find($id);
            if ($entry) {
                $remove = array_map('strval', (array)($_POST['remove_images'] ?? []));
                $moveTo = (array)($_POST['move_images'] ?? []);
                $keep = [];
                $moved = [];
                foreach ($entry['images'] ?? [] as $photo) {
                    if (in_array($photo, $remove, true)) continue;
                    $target = (string)($moveTo[$photo] ?? '');
                    if ($target !== '' && $target !== $id && bot_db()->find($target)) {
                        $moved[$target][] = $photo;
                    } else {
                        $keep[] = $photo;
                    }
                }
                bot_db()->update($id, ['question' => $question, 'answer' => $answer, 'kind' => $kind, 'images' => array_merge($keep, $newPhotos)]);
                foreach ($moved as $target => $photos) {
                    $t = bot_db()->find($target);
                    bot_db()->update($target, ['images' => array_merge($t['images'] ?? [], $photos)]);
                }
                bot_delete_images(array_intersect($entry['images'] ?? [], $remove));
            }
            redirect(base_url('admin/bot.php?saved=1#answers'));
        }
    }

    if ($action === 'settings') {
        $name = trim((string)($_POST['name'] ?? ''));
        $topic = trim((string)($_POST['topic'] ?? ''));
        if ($name === '' || $topic === '') {
            $errors[] = 'The bot name and what it answers questions about are both needed.';
        } else {
            bot_save_settings([
                'name' => $name,
                'topic' => $topic,
                'welcome' => trim((string)($_POST['welcome'] ?? '')),
                'fallback' => trim((string)($_POST['fallback'] ?? '')),
            ]);
            redirect(base_url('admin/bot.php?settings=1#settings'));
        }
    }
}

$s = bot_settings();
$entries = bot_entries();
$count = count($entries);
$editing = isset($_GET['edit']) ? bot_db()->find((string)$_GET['edit']) : null;
$last = $s['last_import'];
$photoCount = 0;
foreach ($entries as $e) $photoCount += count($e['images'] ?? []);

$pageTitle = $s['name'];
$activeAdminNav = 'bot';
require __DIR__ . '/includes/admin_header.php';
?>

<p class="bot-admin-status">
  <?php if ($count): ?>
    <?= $count ?> answer<?= $count === 1 ? '' : 's' ?><?= $photoCount ? " and $photoCount photo" . ($photoCount === 1 ? '' : 's') : '' ?> loaded.
    <?php if ($last): ?>Last import: <?= h($last['source']) ?>, <?= h(date('j M Y', strtotime($last['at']))) ?>.<?php endif; ?>
    The chat button is showing on every page of the site.
  <?php else: ?>
    Nothing imported yet. The <?= h($s['name']) ?> chat button appears on the site once you import a PDF.
  <?php endif; ?>
</p>

<?php if (isset($_GET['imported'])): $n = (int)$_GET['imported']; ?>
  <div class="alert alert-success">
    <?= isset($_GET['appended']) ? 'Added' : 'Imported' ?> <?= $n ?> answer<?= $n === 1 ? '' : 's' ?>.
    Check them below and fix anything that came out wrong, then try a few questions in the test box.
  </div>
<?php endif; ?>
<?php if (isset($_GET['added'])): ?><div class="alert alert-success">Answer added.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Answer saved.</div><?php endif; ?>
<?php if (isset($_GET['settings'])): ?><div class="alert alert-success">Settings saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): $n = (int)$_GET['deleted']; ?>
  <div class="alert alert-success"><?= $n === 1 ? 'Answer deleted.' : "$n answers deleted." ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>

<?php if ($editing): ?>
<div class="panel" id="edit">
  <h2>Edit answer</h2>
  <form method="post" enctype="multipart/form-data" action="<?= h(base_url('admin/bot.php?edit=' . urlencode($editing['id']) . '#edit')) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
    <div class="field"><label>Question or topic</label><input name="question" required dir="auto" value="<?= h($editing['question']) ?>"></div>
    <div class="field"><label>Answer</label><textarea name="answer" required dir="auto" rows="8"><?= h($editing['answer']) ?></textarea></div>

    <?php if (!empty($editing['images'])): ?>
      <div class="field">
        <label>Photos</label>
        <div class="bot-photo-edit">
          <?php foreach ($editing['images'] as $photo): ?>
            <div class="bot-photo-card">
              <a href="<?= h(bot_image_url($photo)) ?>" target="_blank" rel="noopener"><img src="<?= h(bot_image_url($photo)) ?>" alt="" loading="lazy"></a>
              <label><input type="checkbox" name="remove_images[]" value="<?= h($photo) ?>"> Remove</label>
              <select name="move_images[<?= h($photo) ?>]" aria-label="Move this photo to another answer">
                <option value="">Keep with this answer</option>
                <?php foreach ($entries as $other): if ($other['id'] === $editing['id']) continue; ?>
                  <option value="<?= h($other['id']) ?>">Move to: <?= h(bot_excerpt($other['question'], 60)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <div class="field">
      <label>Add photos (JPEG, PNG or WebP)</label>
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>

    <div class="bot-mode"><label><input type="checkbox" name="suggest" <?= ($editing['kind'] ?? 'qa') === 'qa' ? 'checked' : '' ?>> Offer this as a suggestion in the chat</label></div>
    <div style="display:flex;gap:10px;">
      <button class="btn btn-accent btn-sm" type="submit">Save</button>
      <a class="btn btn-ghost btn-sm" href="<?= h(base_url('admin/bot.php#answers')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel" id="import">
  <h2>Import a PDF</h2>
  <p style="color:var(--text-dim);margin-top:-4px;">
    Upload an answer sheet or any document about <?= h($s['topic']) ?>, such as a brochure, manual or presentation.
    Questions and answers are picked up automatically (marked <em>Q:</em> / <em>A:</em>, numbered, or questions ending in "?");
    other documents are learned topic by topic, using their headings. Photos and diagrams come along with the text they belong to,
    and caution/warning notices and disclaimers are left out. English and Arabic both work.
    <?= h($s['name']) ?> only ever replies with what's in your PDF.
  </p>
  <form method="post" id="botImportForm" data-existing="<?= $count ?>" data-upload="<?= h(base_url('admin/bot.php')) ?>">
    <input type="hidden" name="action" value="import">
    <input type="hidden" name="sheet_text" value="">
    <input type="hidden" name="sheet_name" value="">
    <input type="hidden" name="sheet_images" value="">
    <div class="field">
      <label for="botSheetFile">PDF (or a .txt file)</label>
      <input type="file" id="botSheetFile" accept=".pdf,.txt,application/pdf,text/plain">
    </div>
    <details class="bot-paste">
      <summary>Or paste the text instead (for scanned PDFs, Word documents, etc.)</summary>
      <div class="field"><textarea name="pasted_text" dir="auto" placeholder="Q: …&#10;A: …"></textarea></div>
    </details>
    <div class="bot-mode">
      <label><input type="radio" name="mode" value="replace" checked> Replace the current answers</label>
      <label><input type="radio" name="mode" value="append"> Add to the current answers</label>
    </div>
    <p class="bot-import-status" hidden></p>
    <button class="btn btn-accent" type="submit">Import</button>
  </form>
</div>

<?php if ($count): ?>
<div class="panel" id="test">
  <h2>Test the bot</h2>
  <form id="botTestForm" data-api="<?= h(base_url('bot_api.php')) ?>">
    <div class="bot-test-row field" style="margin-bottom:0;">
      <input name="q" dir="auto" placeholder="Ask a question the way a customer would…" autocomplete="off">
      <button class="btn btn-accent btn-sm" type="submit">Ask</button>
    </div>
  </form>
  <div class="bot-test-result" id="botTestResult" hidden></div>
</div>
<?php endif; ?>

<div class="panel" id="answers">
  <h2>Answers (<?= $count ?>)</h2>
  <details style="margin-bottom:18px;" <?= ($action === 'add' && $errors) ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:600;">+ Add a question and answer</summary>
    <form method="post" enctype="multipart/form-data" style="margin-top:14px;">
      <input type="hidden" name="action" value="add">
      <div class="field"><label>Question or topic</label><input name="question" required dir="auto" value="<?= h($action === 'add' ? ($_POST['question'] ?? '') : '') ?>"></div>
      <div class="field"><label>Answer</label><textarea name="answer" required dir="auto" rows="5"><?= h($action === 'add' ? ($_POST['answer'] ?? '') : '') ?></textarea></div>
      <div class="field"><label>Photos (optional)</label><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></div>
      <input type="hidden" name="suggest" value="1">
      <button class="btn btn-accent btn-sm" type="submit">Add</button>
    </form>
  </details>

  <?php if ($count): ?>
  <form method="post" class="bulk-form">
    <div class="table-toolbar">
      <button type="submit" name="bulk_delete" value="1" class="btn btn-danger btn-sm bulk-delete-btn" disabled
              onclick="return confirm('Delete the selected answers and their photos? This cannot be undone.');">
        Delete Selected (<span class="bulk-count">0</span>)
      </button>
    </div>
    <div class="table-scroll">
    <table class="data-table bot-qa-table">
      <thead><tr><th><input type="checkbox" class="select-all"></th><th>Question or topic</th><th>Answer</th><th>Photos</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($entries as $e): $photos = $e['images'] ?? []; ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= h($e['id']) ?>" class="row-check"></td>
          <td class="bot-q" dir="auto">
            <?= h($e['question']) ?>
            <?php if (($e['kind'] ?? 'qa') !== 'qa'): ?><span class="badge bot-kind" title="Searchable, but not offered as a suggestion">info</span><?php endif; ?>
          </td>
          <td class="bot-a" dir="auto"><?= h(bot_excerpt($e['answer'], 180)) ?></td>
          <td class="bot-photos-cell">
            <?php if ($photos): ?>
              <img src="<?= h(bot_image_url($photos[0])) ?>" alt="" loading="lazy">
              <?php if (count($photos) > 1): ?><span class="bot-photo-more">+<?= count($photos) - 1 ?></span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="bot-actions">
            <a class="btn btn-ghost btn-sm" href="<?= h(base_url('admin/bot.php?edit=' . urlencode($e['id']) . '#edit')) ?>">Edit</a>
            <button type="submit" name="delete_id" value="<?= h($e['id']) ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this answer and its photos?');">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>
  <?php endif; ?>
</div>

<div class="panel" id="settings">
  <h2>Bot settings</h2>
  <form method="post">
    <input type="hidden" name="action" value="settings">
    <div class="form-grid">
      <div class="field"><label>Bot name</label><input name="name" required value="<?= h($s['name']) ?>"></div>
      <div class="field"><label>Answers questions about</label><input name="topic" required value="<?= h($s['topic']) ?>"></div>
      <div class="field field-full">
        <label>Welcome message (leave empty for the automatic one)</label>
        <textarea name="welcome" dir="auto" rows="2" placeholder="<?= h(bot_welcome(array_merge($s, ['welcome' => '']))) ?>"><?= h($s['welcome']) ?></textarea>
      </div>
      <div class="field field-full">
        <label>Reply when it doesn't know the answer (leave empty for the automatic one)</label>
        <textarea name="fallback" dir="auto" rows="2" placeholder="<?= h(bot_fallback(array_merge($s, ['fallback' => '']))) ?>"><?= h($s['fallback']) ?></textarea>
      </div>
    </div>
    <button class="btn btn-accent btn-sm" type="submit">Save settings</button>
  </form>
</div>

<script type="module" src="<?= base_url('assets/js/bot-admin.js') ?>"></script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
