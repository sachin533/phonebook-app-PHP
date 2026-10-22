<?php
/**
 * inc/footer.php - the bottom of every page.
 */
declare(strict_types=1);

$layout = $layout ?? 'app';
?>
<?php if ($layout === 'auth'): ?>
    </div>
  </main>
</div>
</body>
</html>
<?php else: ?>
    </main>
  </div>
</div>
</body>
</html>

<!-- The confirmation dialog for destructive actions. It is a real <dialog>,
     so it is keyboard accessible and the browser handles the focus. -->
<dialog class="modal" id="confirmDialog" aria-labelledby="confirmTitle">
  <div class="modal-inner">
    <h2 id="confirmTitle">Are you sure?</h2>
    <p id="confirmMessage">This action cannot be undone.</p>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
      <button type="button" class="btn btn-danger" data-confirm-accept>Delete</button>
    </div>
  </div>
</dialog>
<?php endif; ?>

<script src="/static/app.js" defer></script>
</body>
</html>
