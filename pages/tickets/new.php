<?php

/**
 * Form for a new ticket
 *
 * This file is part of the OSIRIS package.
 *
 * @link        /tickets/new
 *
 * @package     OSIRIS
 * @license     MIT
 *
 * @var Tickets $T
 * @var string|null $ticketPage the page the user came from
 * @var string|null $ticketCategory preselected category
 */
$categories = Tickets::categories();
if (!array_key_exists($ticketCategory ?? '', $categories)) $ticketCategory = null;
?>
<style>
    .ticket-categories {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .ticket-categories input {
        position: absolute;
        opacity: 0;
    }

    .ticket-categories label {
        display: flex;
        align-items: center;
        gap: 1rem;
        height: 100%;
        padding: 1rem 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: var(--box-bg-color, #fff);
        cursor: pointer;
        font-weight: 500;
    }

    .ticket-categories label i {
        font-size: 2.4rem;
    }

    .ticket-categories input:checked+label {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px var(--primary-color);
    }

    .ticket-categories input:focus-visible+label {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    #ticket-dropzone {
        border: 2px dashed var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        text-align: center;
        color: var(--muted-color);
    }

    #ticket-dropzone.dragover {
        border-color: var(--primary-color);
    }

    #ticket-file-list {
        list-style: none;
        padding: 0;
        margin: 1rem 0 0;
        text-align: left;
    }

    #ticket-file-list img {
        max-height: 6rem;
        max-width: 12rem;
        margin-right: 1rem;
        vertical-align: middle;
        border: 1px solid var(--border-color);
    }
</style>

<div class="container w-800 mw-full">
    <h1>
        <i class="ph-duotone ph-chat-circle-dots"></i>
        <?= lang('New ticket', 'Neues Ticket') ?>
    </h1>
    <p class="text-muted">
        <?= lang(
            'Report a problem, request a change or share an idea. Your ticket goes to the OSIRIS administrators of your institution, and you will be notified when something happens.',
            'Melde ein Problem, wünsche dir eine Änderung oder teile eine Idee. Dein Ticket geht an die OSIRIS-Administration deiner Einrichtung, und du wirst benachrichtigt, sobald sich etwas tut.'
        ) ?>
    </p>

    <form action="<?= ROOTPATH ?>/crud/tickets/create" method="post" enctype="multipart/form-data" id="ticket-form">
        <h5><?= lang('What is it about?', 'Worum geht es?') ?></h5>
        <div class="ticket-categories">
            <?php foreach ($categories as $key => $c) { ?>
                <div>
                    <input type="radio" name="ticket[category]" id="cat-<?= $key ?>" value="<?= $key ?>" data-hint="<?= e($c['hint']) ?>" required <?= $ticketCategory == $key ? 'checked' : '' ?>>
                    <label for="cat-<?= $key ?>">
                        <i class="ph-duotone ph-<?= $c['icon'] ?> text-<?= $c['color'] ?>" aria-hidden="true"></i>
                        <?= $c['label'] ?>
                    </label>
                </div>
            <?php } ?>
        </div>

        <div class="form-group">
            <label for="ticket-title" class="required"><?= lang('Short summary', 'Kurze Zusammenfassung') ?></label>
            <input type="text" class="form-control" name="ticket[title]" id="ticket-title" maxlength="200" required autocomplete="off">
            <div id="ticket-similar" class="alert signal mt-10" style="display:none">
                <b><?= lang('Has this already been reported?', 'Wurde das schon gemeldet?') ?></b>
                <?= lang('If one of these tickets fits, open it and click "Affects me too" instead of creating a new one.', 'Wenn eines dieser Tickets passt, öffne es und klicke auf „Betrifft mich auch“, statt ein neues anzulegen.') ?>
                <ul class="mb-0 mt-5"></ul>
            </div>
        </div>

        <div class="form-group">
            <label for="ticket-description" class="required"><?= lang('Description', 'Beschreibung') ?></label>
            <p class="text-muted font-size-12 my-0" id="ticket-hint"></p>
            <textarea class="form-control" name="ticket[description]" id="ticket-description" rows="8" required></textarea>
        </div>

        <div class="form-group">
            <label><?= lang('Screenshots or files (optional)', 'Screenshots oder Dateien (optional)') ?></label>
            <div id="ticket-dropzone">
                <i class="ph ph-image ph-2x" aria-hidden="true"></i><br>
                <?= lang('Paste a screenshot with Ctrl+V, drag files here, or', 'Screenshot mit Strg+V einfügen, Dateien hierher ziehen oder') ?>
                <label for="ticket-files" class="link mb-0" style="cursor:pointer"><?= lang('choose files', 'Dateien auswählen') ?></label>.
                <input type="file" name="files[]" id="ticket-files" multiple accept="<?= '.' . implode(',.', Tickets::EXTENSIONS) ?>" class="hidden">
                <ul id="ticket-file-list"></ul>
            </div>
            <small class="text-muted">
                <?= lang('Max. 10 MB per file. Allowed: ', 'Max. 10 MB pro Datei. Erlaubt: ') . implode(', ', Tickets::EXTENSIONS) ?>
            </small>
        </div>

        <?php if (!empty($ticketPage)) { ?>
            <div class="form-group" id="ticket-page-group">
                <div class="custom-checkbox">
                    <input type="checkbox" id="ticket-page-check" checked onchange="$('#ticket-page').prop('disabled', !this.checked)">
                    <label for="ticket-page-check">
                        <?= lang('Refers to the page', 'Bezieht sich auf die Seite') ?>
                        <code><?= e($ticketPage) ?></code>
                    </label>
                </div>
                <input type="hidden" name="ticket[page]" id="ticket-page" value="<?= e($ticketPage) ?>">
            </div>
        <?php } ?>

        <?php if ($T->publicEnabled()) { ?>
            <div class="form-group">
                <div class="custom-checkbox">
                    <input type="checkbox" name="ticket[public]" id="ticket-public" value="1" checked>
                    <label for="ticket-public">
                        <?= lang('Visible to all users', 'Für alle Nutzenden sichtbar') ?>
                    </label>
                </div>
                <small class="text-muted">
                    <?= lang(
                        'Others with the same concern can join your ticket instead of reporting it again. Uncheck this if your ticket contains personal information.',
                        'Andere mit demselben Anliegen können sich deinem Ticket anschließen, statt es erneut zu melden. Nimm den Haken heraus, wenn dein Ticket persönliche Angaben enthält.'
                    ) ?>
                </small>
            </div>
        <?php } ?>

        <button type="submit" class="btn primary">
            <i class="ph ph-paper-plane-tilt"></i>
            <?= lang('Submit ticket', 'Ticket absenden') ?>
        </button>
    </form>
</div>

<script>
    (function() {
        const input = document.getElementById('ticket-files');
        const list = document.getElementById('ticket-file-list');
        const zone = document.getElementById('ticket-dropzone');
        let files = new DataTransfer();

        function render() {
            input.files = files.files;
            list.innerHTML = '';
            Array.from(files.files).forEach(function(file, i) {
                const li = document.createElement('li');
                li.className = 'mb-5';
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.alt = '';
                    li.appendChild(img);
                }
                li.appendChild(document.createTextNode(file.name + ' '));
                const btn = document.createElement('a');
                btn.href = '#';
                btn.className = 'text-danger';
                btn.title = '<?= lang('Remove', 'Entfernen') ?>';
                btn.innerHTML = '<i class="ph ph-x"></i>';
                btn.onclick = function(e) {
                    e.preventDefault();
                    const next = new DataTransfer();
                    Array.from(files.files).forEach(function(f, j) {
                        if (j !== i) next.items.add(f);
                    });
                    files = next;
                    render();
                };
                li.appendChild(btn);
                list.appendChild(li);
            });
        }

        function add(fileList, rename) {
            Array.from(fileList).forEach(function(file) {
                // pasted screenshots are all called image.png
                if (rename && file.type.startsWith('image/')) {
                    const ext = file.type.split('/')[1] || 'png';
                    const stamp = new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-');
                    file = new File([file], 'screenshot-' + stamp + '-' + (files.files.length + 1) + '.' + ext, {
                        type: file.type
                    });
                }
                files.items.add(file);
            });
            render();
        }

        input.addEventListener('change', function() {
            const chosen = Array.from(input.files);
            input.files = files.files;
            add(chosen, false);
        });
        document.addEventListener('paste', function(e) {
            const pasted = Array.from(e.clipboardData?.files || []).filter(f => f.type.startsWith('image/'));
            if (pasted.length === 0) return;
            e.preventDefault();
            add(pasted, true);
        });
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            add(e.dataTransfer.files, false);
        });

        function updateHint() {
            const checked = document.querySelector('input[name="ticket[category]"]:checked');
            document.getElementById('ticket-hint').textContent = checked ? checked.dataset.hint : '';
        }
        document.querySelectorAll('input[name="ticket[category]"]').forEach(function(el) {
            el.addEventListener('change', updateHint);
        });
        updateHint();

        // existing tickets with similar words, to avoid duplicates
        const similar = document.getElementById('ticket-similar');
        let timer = null;
        let lastQuery = '';
        document.getElementById('ticket-title').addEventListener('input', function() {
            clearTimeout(timer);
            const q = this.value.trim();
            timer = setTimeout(function() {
                if (q === lastQuery) return;
                lastQuery = q;
                if (q.length < 3) {
                    similar.style.display = 'none';
                    return;
                }
                fetch('<?= ROOTPATH ?>/tickets/similar?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(function(found) {
                        if (q !== lastQuery) return;
                        const ul = similar.querySelector('ul');
                        ul.innerHTML = '';
                        found.forEach(function(t) {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.href = '<?= ROOTPATH ?>/tickets/view/' + t.id;
                            a.target = '_blank';
                            a.textContent = '#' + t.number + ' ' + t.title;
                            li.appendChild(a);
                            li.appendChild(document.createTextNode(' (' + t.status + ', <?= lang('affected', 'betroffen') ?>: ' + t.affected + ')'));
                            ul.appendChild(li);
                        });
                        similar.style.display = found.length ? '' : 'none';
                    })
                    .catch(function() {
                        similar.style.display = 'none';
                    });
            }, 400);
        });
    })();
</script>
