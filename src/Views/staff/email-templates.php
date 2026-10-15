<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;
use Bulbula\Services\EmailTemplateCatalog;
$subject = (string)($selectedRevision['subject_template'] ?? $initial['subject']);
$html = (string)($selectedRevision['html_template'] ?? $initial['html']);
$text = (string)($selectedRevision['text_template'] ?? $initial['text']);
$project = json_decode((string)($selectedRevision['project_json'] ?? ''), true);
$data = json_encode(['html'=>$html, 'project'=>$project], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<section class="b-email-page">
  <div class="b-email-page__intro"><div><span class="b-badge b-badge--orange">Administrator workspace</span><h1>Email design studio</h1><p>Choose a message, design its layout, save a draft and publish only when you're happy. No email is sent by this editor.</p></div><a class="b-btn b-btn--outline" href="/staff/settings">SMTP settings</a></div>
  <div class="b-email-page__layout">
    <aside class="b-email-page__catalog" aria-label="Transactional email types">
      <h2>Choose an email</h2>
      <?php foreach ($catalog as $item): ?><a class="b-email-page__type <?= $item['key'] === $key ? 'is-active' : '' ?>" href="/staff/email-templates?type=<?= rawurlencode($item['key']) ?>" <?= $item['key'] === $key ? 'aria-current="page"' : '' ?>><span><?= View::e($item['label']) ?></span><small><?= $item['revision_id'] ? 'Published' : 'Default' ?></small></a><?php endforeach; ?>
    </aside>
    <div class="b-email-page__main">
      <header class="b-email-page__header"><div><p class="b-email-page__eyebrow">SELECTED MESSAGE</p><h2><?= View::e(EmailTemplateCatalog::LABELS[$key]) ?></h2><p><?= $activeId ? 'Published revision #' . (int)$activeId : 'Using the built-in email until a design is published.' ?> <?php if ($selectedRevision): ?>Editing saved revision #<?= (int)$selectedRevision['id'] ?>.<?php endif; ?></p></div><button id="email-preview" type="button" class="b-btn b-btn--outline">Preview with sample content</button></header>
      <div class="b-email-page__notice">Design freely, but include <strong><?= View::e($requiredToken) ?></strong> in both the visual design and plain-text version. <?php if (EmailTemplateCatalog::isCode($key)): ?>Also include <strong>{{minutes}}</strong> in both. These values are filled in securely when an actual email is sent.<?php else: ?>This placeholder supplies the essential event details.<?php endif; ?> Use only HTTPS Bulbula-hosted links and images.</div>
      <script id="email-template-data" type="application/json"><?= $data ?></script>
      <div id="b-email-editor" aria-label="Drag and drop email layout editor"></div>
      <div id="email-preview-wrap" class="b-email-page__preview" hidden><h3>Sample preview — not an actual email</h3><iframe id="email-preview-frame" title="Sample email design" sandbox="" referrerpolicy="no-referrer"></iframe></div>
      <form id="email-save-form" action="/staff/email-templates/save" method="post" class="b-email-page__form">
        <?= Csrf::field() ?><input type="hidden" name="template_key" value="<?= View::e($key) ?>">
        <input id="email-html" type="hidden" name="html"><input id="email-project" type="hidden" name="project">
        <div><label for="email-subject">Email subject</label><input id="email-subject" class="b-input" name="subject" required maxlength="180" value="<?= View::e($subject) ?>"><small>Use {{subject}} to keep the event's standard subject.</small></div>
        <div><label for="email-plain">Plain-text version (for accessibility and text-only inboxes)</label><textarea id="email-plain" name="text" class="b-input" rows="5" required maxlength="12000"><?= View::e($text) ?></textarea></div>
        <div class="b-email-page__actions"><button type="submit" class="b-btn b-btn--primary">Save new draft</button><p id="email-editor-status" role="status" aria-live="polite"></p></div>
      </form>
      <?php if ($revisions): ?><section class="b-email-page__revisions"><h3>Saved revisions</h3><p>Publishing changes future sends only. You can publish an older revision to roll back.</p><div class="b-email-page__revision-list"><?php foreach ($revisions as $row): ?><div class="b-email-page__revision"><a href="/staff/email-templates?type=<?= rawurlencode($key) ?>&amp;revision=<?= (int)$row['id'] ?>">Version <?= (int)$row['version'] ?></a><small><?= View::e($row['created_at']) ?> <?= (int)$row['id'] === $activeId ? ' · LIVE' : ' · Draft / archived' ?></small><?php if ((int)$row['id'] !== $activeId): ?><form method="post" action="/staff/email-templates/publish"><?= Csrf::field() ?><input type="hidden" name="template_key" value="<?= View::e($key) ?>"><input type="hidden" name="revision_id" value="<?= (int)$row['id'] ?>"><button class="b-btn b-btn--outline" type="submit">Publish this version</button></form><?php endif; ?></div><?php endforeach; ?></div></section><?php endif; ?>
    </div>
  </div>
</section>
