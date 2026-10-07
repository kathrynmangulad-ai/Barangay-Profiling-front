        </div> 
    </main>
</div> 

<?php $flash_ok = flash('ok'); $flash_err = flash('err'); ?>
<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false">
    <?php if ($flash_ok): ?>
        <div class="toast toast--success" data-toast role="status">
            <span class="toast__ico" aria-hidden="true"><?= icon('check-circle') ?></span>
            <span class="toast__msg"><?= e($flash_ok) ?></span>
            <button class="toast__close" type="button" data-toast-close aria-label="Dismiss notification"><?= icon('x', 'ico ico--sm') ?></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="toast toast--error" data-toast role="alert">
            <span class="toast__ico" aria-hidden="true"><?= icon('alert-circle') ?></span>
            <span class="toast__msg"><?= e($flash_err) ?></span>
            <button class="toast__close" type="button" data-toast-close aria-label="Dismiss notification"><?= icon('x', 'ico ico--sm') ?></button>
        </div>
    <?php endif; ?>
</div>

<div class="confirm" id="confirmDialog" hidden>
    <div class="confirm__backdrop" data-confirm-cancel></div>
    <div class="confirm__box" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmDesc">
        <div class="confirm__icon" aria-hidden="true"><?= icon('alert-triangle') ?></div>
        <h2 class="confirm__title" id="confirmTitle">Please confirm</h2>
        <p class="confirm__desc" id="confirmDesc">This action cannot be undone.</p>
        <div class="confirm__actions">
            <button type="button" class="btn btn--ghost" data-confirm-cancel>Cancel</button>
            <button type="button" class="btn btn--danger" data-confirm-accept>Confirm</button>
        </div>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($page_scripts ?? []) as $src): ?>
<script src="<?= e(preg_match('~^https?://~', $src) ? $src : asset(preg_replace('~^assets/~', '', $src))) ?>"></script>
<?php endforeach; ?>
</body>
</html>
