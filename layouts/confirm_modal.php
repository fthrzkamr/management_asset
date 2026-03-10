<!-- Modal Konfirmasi Universal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Konfirmasi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-primary" id="confirmModalOk">
                    <i class="fas fa-check"></i> Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus (Danger) -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-trash-alt"></i> Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="deleteModalMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-danger" id="deleteModalOk">
                    <i class="fas fa-trash-alt"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to load before defining showConfirm
(function() {
    // Store pending confirms
    var pendingConfirms = [];
    var jQueryReady = false;
    
    // Check if jQuery is ready
    function checkJQuery() {
        if (typeof jQuery !== 'undefined') {
            jQueryReady = true;
            // Process any pending confirms
            while (pendingConfirms.length > 0) {
                var pending = pendingConfirms.shift();
                showConfirmInternal(pending.title, pending.message, pending.onConfirm, pending.onCancel, pending.type);
            }
            return true;
        }
        return false;
    }
    
    // Internal function that actually shows the modal (requires jQuery)
    function showConfirmInternal(title, message, onConfirm, onCancel, type) {
        // Support old signature: showConfirm(message, callback, type)
        if (typeof title === 'string' && typeof message === 'function') {
            type = onConfirm || 'confirm';
            onCancel = null;
            onConfirm = message;
            message = title;
            title = type === 'delete' ? 'Konfirmasi Hapus' : 'Konfirmasi';
        }
        
        var isDelete = type === 'delete';
        var modalId = isDelete ? '#deleteModal' : '#confirmModal';
        var messageId = isDelete ? '#deleteModalMessage' : '#confirmModalMessage';
        var titleId = isDelete ? '#deleteModalLabel' : '#confirmModalLabel';
        var btnId = isDelete ? '#deleteModalOk' : '#confirmModalOk';
        
        // Set title and message dengan line break support
        if (title) {
            jQuery(titleId).html('<i class="fas fa-' + (isDelete ? 'trash-alt' : 'exclamation-triangle') + '"></i> ' + title);
        }
        jQuery(messageId).html(message.replace(/\n/g, '<br>'));
        
        // Manage focus to avoid aria-hidden focus conflict
        var previousActive = document.activeElement;
        try { if (previousActive && typeof previousActive.blur === 'function') previousActive.blur(); } catch (e) {}

        // Show modal with focus disabled initially
        try {
            jQuery(modalId).modal({ show: true, focus: false });
        } catch (e) {
            jQuery(modalId).modal('show');
        }

        // After modal shown, remove aria-hidden and set focus
        jQuery(modalId).off('shown.bs.modal').on('shown.bs.modal', function() {
            jQuery(this).removeAttr('aria-hidden');
            try { jQuery(btnId).focus(); } catch (e) {}
        });

        // When modal hides, restore focus
        jQuery(modalId).off('hidden.bs.modal').on('hidden.bs.modal', function() {
            try {
                if (previousActive && typeof previousActive.focus === 'function') previousActive.focus();
            } catch (e) {}
        });
        
        // Handle OK button
        jQuery(btnId).off('click').on('click', function() {
            jQuery(modalId).modal('hide');
            if (onConfirm) onConfirm();
        });
        
        // Handle cancel/close
        jQuery(modalId).find('.btn-secondary, .close').off('click').on('click', function() {
            jQuery(modalId).modal('hide');
            if (onCancel) onCancel();
        });
    }
    
    // Public API
    window.showConfirm = function(title, message, onConfirm, onCancel, type) {
        type = type || 'confirm';
        
        if (!checkJQuery()) {
            // jQuery not ready yet, queue this request
            pendingConfirms.push({
                title: title,
                message: message,
                onConfirm: onConfirm,
                onCancel: onCancel,
                type: type
            });
            
            // Try again soon
            setTimeout(checkJQuery, 50);
            return;
        }
        
        showConfirmInternal(title, message, onConfirm, onCancel, type);
    };
})();

// MutationObserver to forcibly remove aria-hidden from modals
(function() {
    function removeAriaHidden(el) {
        try {
            if (el && el.hasAttribute && el.hasAttribute('aria-hidden')) el.removeAttribute('aria-hidden');
        } catch (e) {}
    }

    try {
        var modalIds = ['confirmModal', 'deleteModal'];
        modalIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            removeAriaHidden(el);
            var mo = new MutationObserver(function(mutations) {
                mutations.forEach(function(m) {
                    if (m.attributeName === 'aria-hidden') removeAriaHidden(el);
                });
            });
            mo.observe(el, { attributes: true, attributeFilter: ['aria-hidden'] });
        });
    } catch (e) {}
})();

// Event delegation untuk button dengan class btn-delete-confirm
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        $(document).off('click', '.btn-delete-confirm').on('click', '.btn-delete-confirm', function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            var message = $(this).data('message') || 'Yakin ingin menghapus data ini?';

            // showConfirm supports the old signature showConfirm(message, callback, type)
            // The callback is invoked when the user confirms. Do not expect a parameter.
            showConfirm(message, function() {
                window.location.href = href;
            }, 'delete');
        });
    });
}
</script>
