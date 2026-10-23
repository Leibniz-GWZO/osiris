<?php

/**
 * View a ticket
 *
 * This file is part of the OSIRIS package.
 *
 * @link        /tickets/view/<id>
 *
 * @package     OSIRIS
 * @license     MIT
 *
 * @var Tickets $T
 * @var array $ticket
 */
$manage = $T->canManage();
$id = strval($ticket['_id']);
$comments = DB::doc2Arr($ticket['comments'] ?? []);
if (!$manage) {
    $comments = array_filter($comments, fn($c) => empty($c['internal']));
}
$files = DB::doc2Arr($ticket['files'] ?? []);
$followers = DB::doc2Arr($ticket['followers'] ?? []);
$fileUrl = ROOTPATH . '/tickets/files/' . $id . '/';

function ticket_time($datetime)
{
    return date('d.m.Y, H:i', strtotime($datetime));
}
?>
<style>
    .ticket-text {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .ticket-comment {
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
        background: var(--box-bg-color, #fff);
    }

    .ticket-comment.from-reporter {
        border-left: 4px solid var(--primary-color);
    }

    .ticket-comment.internal {
        background: var(--signal-color-very-light, #fff8e6);
        border-style: dashed;
    }

    .ticket-comment .meta {
        font-size: 1.2rem;
        color: var(--muted-color);
        margin-bottom: 0.5rem;
    }

    .ticket-images a {
        display: inline-block;
        margin: 0 1rem 1rem 0;
    }

    .ticket-images img {
        max-height: 14rem;
        max-width: 100%;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
    }
</style>

<div class="d-flex align-items-center flex-wrap">
    <h1 class="mr-20">
        <span class="text-muted">#<?= intval($ticket['number']) ?></span>
        <?= e($ticket['title']) ?>
    </h1>
    <?= Tickets::statusBadge($ticket['status']) ?>
    <span class="ml-5"><?= Tickets::categoryBadge($ticket['category']) ?></span>
    <?php if ($T->publicEnabled()) { ?>
        <span class="badge ml-5" title="<?= $T->isPublic($ticket) ? lang('All users can see this ticket.', 'Alle Nutzenden können dieses Ticket sehen.') : lang('Only the reporter and the administrators can see this ticket.', 'Nur die meldende Person und die Administration sehen dieses Ticket.') ?>">
            <?php if ($T->isPublic($ticket)) { ?>
                <i class="ph ph-eye"></i> <?= lang('Visible to all', 'Für alle sichtbar') ?>
            <?php } else { ?>
                <i class="ph ph-lock-simple"></i> <?= lang('Private', 'Privat') ?>
            <?php } ?>
        </span>
    <?php } ?>
</div>

<?php if ($ticket['status'] == 'waiting' && $T->isReporter($ticket)) { ?>
    <div class="alert danger mb-20">
        <i class="ph ph-chat-circle-text"></i>
        <?= lang('The administrators have a question for you. Please answer below.', 'Die Administration hat eine Rückfrage an dich. Bitte antworte unten.') ?>
    </div>
<?php } ?>

<div class="row row-eq-spacing">
    <div class="col-lg-8">
        <div class="ticket-comment from-reporter">
            <div class="meta">
                <b><?= e($DB->getNameFromId($ticket['created_by'])) ?></b>
                · <?= ticket_time($ticket['created_at']) ?>
            </div>
            <div class="ticket-text"><?= e($ticket['description']) ?></div>
        </div>

        <?php if (!empty($files)) { ?>
            <div class="ticket-images">
                <?php foreach ($files as $f) {
                    if (!Tickets::isImage($f)) continue; ?>
                    <a href="<?= $fileUrl . e($f['id']) ?>?inline=1" target="_blank" rel="noopener">
                        <img src="<?= $fileUrl . e($f['id']) ?>?inline=1" alt="<?= e($f['name']) ?>" loading="lazy">
                    </a>
                <?php } ?>
            </div>
            <ul style="list-style:none;padding-left:0">
                <?php foreach ($files as $f) { ?>
                    <li>
                        <i class="ph ph-paperclip"></i>
                        <a href="<?= $fileUrl . e($f['id']) ?>"><?= e($f['name']) ?></a>
                        <small class="text-muted">(<?= formatBytes($f['size']) ?>, <?= e($DB->getNameFromId($f['by'])) ?>)</small>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>

        <h3 id="comments"><?= lang('Conversation', 'Verlauf') ?></h3>

        <?php if (empty($comments)) { ?>
            <p class="text-muted"><?= lang('No replies yet.', 'Noch keine Antworten.') ?></p>
        <?php } ?>
        <?php foreach ($comments as $c) {
            $classes = ['ticket-comment'];
            if (!empty($c['internal'])) $classes[] = 'internal';
            if ($c['by'] == $ticket['created_by']) $classes[] = 'from-reporter';
        ?>
            <div class="<?= implode(' ', $classes) ?>">
                <div class="meta">
                    <b><?= e($DB->getNameFromId($c['by'])) ?></b>
                    · <?= ticket_time($c['at']) ?>
                    <?php if (!empty($c['internal'])) { ?>
                        · <span class="text-signal"><i class="ph ph-lock-simple"></i> <?= lang('internal note, not visible to the reporter', 'interne Notiz, für die meldende Person nicht sichtbar') ?></span>
                    <?php } ?>
                </div>
                <div class="ticket-text"><?= e($c['text']) ?></div>
            </div>
        <?php } ?>

        <form action="<?= ROOTPATH ?>/crud/tickets/comment/<?= $id ?>" method="post" enctype="multipart/form-data" class="box padded mt-20">
            <div class="form-group">
                <label for="ticket-comment"><?= lang('Write a reply', 'Antwort schreiben') ?></label>
                <textarea class="form-control" name="comment" id="ticket-comment" rows="4"></textarea>
            </div>
            <div class="form-group">
                <input type="file" name="files[]" multiple accept="<?= '.' . implode(',.', Tickets::EXTENSIONS) ?>">
            </div>
            <?php if ($manage) { ?>
                <div class="form-group custom-checkbox">
                    <input type="checkbox" name="internal" id="ticket-internal" value="1">
                    <label for="ticket-internal">
                        <i class="ph ph-lock-simple"></i>
                        <?= lang('Internal note (only visible to administrators)', 'Interne Notiz (nur für die Administration sichtbar)') ?>
                    </label>
                </div>
            <?php } ?>
            <button type="submit" class="btn primary">
                <i class="ph ph-paper-plane-tilt"></i>
                <?= lang('Send', 'Senden') ?>
            </button>
        </form>
    </div>

    <div class="col-lg-4">
        <?php if ($T->isPublic($ticket) && !$T->isReporter($ticket)) { ?>
            <form action="<?= ROOTPATH ?>/crud/tickets/follow/<?= $id ?>" method="post" class="mb-10">
                <?php if ($T->isFollower($ticket)) { ?>
                    <input type="hidden" name="follow" value="0">
                    <button type="submit" class="btn block">
                        <i class="ph ph-check"></i>
                        <?= lang('You are affected too', 'Du bist auch betroffen') ?>
                        <small class="text-muted">(<?= lang('leave', 'zurückziehen') ?>)</small>
                    </button>
                <?php } else { ?>
                    <input type="hidden" name="follow" value="1">
                    <button type="submit" class="btn primary block">
                        <i class="ph ph-hand-pointing"></i>
                        <?= lang('Affects me too', 'Betrifft mich auch') ?>
                    </button>
                    <small class="text-muted"><?= lang('You will be notified about the progress.', 'Du wirst über den Fortschritt benachrichtigt.') ?></small>
                <?php } ?>
            </form>
        <?php } ?>
        <div class="box padded">
            <table class="table simple small">
                <tr>
                    <td><?= lang('Submitted by', 'Eingereicht von') ?></td>
                    <td><a href="<?= ROOTPATH ?>/profile/<?= e($ticket['created_by']) ?>"><?= e($DB->getNameFromId($ticket['created_by'])) ?></a></td>
                </tr>
                <tr>
                    <td><?= lang('Submitted', 'Eingereicht') ?></td>
                    <td><?= ticket_time($ticket['created_at']) ?></td>
                </tr>
                <?php if (!empty($ticket['page'])) { ?>
                    <tr>
                        <td><?= lang('Page', 'Seite') ?></td>
                        <td><a href="<?= ROOTPATH . e($ticket['page']) ?>" style="overflow-wrap:anywhere"><?= e($ticket['page']) ?></a></td>
                    </tr>
                <?php } ?>
                <?php if (!empty($followers) || $T->isPublic($ticket)) { ?>
                    <tr>
                        <td><?= lang('Affected', 'Betroffen') ?></td>
                        <td>
                            <?= 1 + count($followers) ?> <?= count($followers) ? lang('people', 'Personen') : lang('person', 'Person') ?>
                            <?php if ($manage && !empty($followers)) { ?>
                                <br><small class="text-muted"><?= e(implode(', ', array_map(fn($u) => $DB->getNameFromId($u), $followers))) ?></small>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!empty($ticket['assigned_to'])) { ?>
                    <tr>
                        <td><?= lang('Handled by', 'Bearbeitet von') ?></td>
                        <td><?= e($DB->getNameFromId($ticket['assigned_to'])) ?></td>
                    </tr>
                <?php } ?>
                <?php if ($manage) { ?>
                    <tr>
                        <td>OSIRIS</td>
                        <td><?= e($ticket['context']['version'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td>Browser</td>
                        <td class="font-size-12" style="overflow-wrap:anywhere"><?= e($ticket['context']['user_agent'] ?? '') ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <?php if ($manage) { ?>
            <form action="<?= ROOTPATH ?>/crud/tickets/update/<?= $id ?>" method="post" class="box padded">
                <h5 class="mt-0"><?= lang('Manage', 'Bearbeiten') ?></h5>
                <div class="form-group">
                    <label for="ticket-status">Status</label>
                    <select name="ticket[status]" id="ticket-status" class="form-control">
                        <?php foreach (Tickets::statuses() as $key => $s) { ?>
                            <option value="<?= $key ?>" <?= $ticket['status'] == $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                        <?php } ?>
                    </select>
                    <small class="text-muted"><?= lang('The reporter is notified about status changes.', 'Die meldende Person wird über Statusänderungen benachrichtigt.') ?></small>
                </div>
                <div class="form-group">
                    <label for="ticket-assigned"><?= lang('Assigned to', 'Zugewiesen an') ?></label>
                    <select name="ticket[assigned_to]" id="ticket-assigned" class="form-control">
                        <option value="">–</option>
                        <?php foreach ($T->managers() as $u) { ?>
                            <option value="<?= e($u) ?>" <?= ($ticket['assigned_to'] ?? null) == $u ? 'selected' : '' ?>><?= e($DB->getNameFromId($u)) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ticket-priority"><?= lang('Priority', 'Priorität') ?></label>
                    <select name="ticket[priority]" id="ticket-priority" class="form-control">
                        <?php foreach (Tickets::priorities() as $key => $label) { ?>
                            <option value="<?= $key ?>" <?= ($ticket['priority'] ?? 'normal') == $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ticket-category"><?= lang('Category', 'Kategorie') ?></label>
                    <select name="ticket[category]" id="ticket-category" class="form-control">
                        <?php foreach (Tickets::categories(true) as $key => $c) {
                            if ($c['inactive'] && $ticket['category'] != $key) continue; ?>
                            <option value="<?= e($key) ?>" <?= $ticket['category'] == $key ? 'selected' : '' ?>><?= $c['label'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if ($T->publicEnabled()) { ?>
                    <div class="form-group">
                        <label for="ticket-public"><?= lang('Visibility', 'Sichtbarkeit') ?></label>
                        <select name="ticket[public]" id="ticket-public" class="form-control">
                            <option value="1" <?= $T->isPublic($ticket) ? 'selected' : '' ?>><?= lang('Visible to all users', 'Für alle Nutzenden sichtbar') ?></option>
                            <option value="0" <?= $T->isPublic($ticket) ? '' : 'selected' ?>><?= lang('Private', 'Privat') ?></option>
                        </select>
                    </div>
                <?php } ?>
                <button type="submit" class="btn primary"><?= lang('Save', 'Speichern') ?></button>
            </form>

            <details class="box padded">
                <summary><?= lang('History', 'Änderungsprotokoll') ?></summary>
                <ul class="font-size-12 mt-10" style="list-style:none;padding-left:0">
                    <?php foreach (array_reverse(DB::doc2Arr($ticket['history'] ?? [])) as $h) {
                        $what = match ($h['action']) {
                            'created' => lang('created the ticket', 'hat das Ticket erstellt'),
                            'status' => 'Status: ' . e(Tickets::statuses()[$h['from']]['label'] ?? $h['from']) . ' → ' . e(Tickets::statuses()[$h['to']]['label'] ?? $h['to']),
                            'priority' => lang('Priority', 'Priorität') . ': ' . e(Tickets::priorities()[$h['to']] ?? $h['to']),
                            'category' => lang('Category', 'Kategorie') . ': ' . (Tickets::categories(true)[$h['to']]['label'] ?? e($h['to'])),
                            'assigned_to' => lang('Assigned to', 'Zugewiesen an') . ': ' . (empty($h['to']) ? '–' : e($DB->getNameFromId($h['to']))),
                            'public' => $h['to'] ? lang('made visible to all', 'für alle sichtbar gemacht') : lang('made private', 'auf privat gestellt'),
                            default => e($h['action']),
                        };
                    ?>
                        <li class="mb-5">
                            <span class="text-muted"><?= ticket_time($h['at']) ?></span>
                            <?= e($DB->getNameFromId($h['by'])) ?>: <?= $what ?>
                        </li>
                    <?php } ?>
                </ul>
            </details>

            <form action="<?= ROOTPATH ?>/crud/tickets/delete/<?= $id ?>" method="post" onsubmit="return confirm('<?= lang('Delete this ticket including all files?', 'Dieses Ticket samt aller Dateien löschen?') ?>')">
                <button type="submit" class="btn danger small">
                    <i class="ph ph-trash"></i>
                    <?= lang('Delete ticket', 'Ticket löschen') ?>
                </button>
            </form>
        <?php } ?>
    </div>
</div>
