<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />


<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"
        integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3"
        crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"
        integrity="sha384-cuYeSxntonz0PPNlHhBs68uyIAVpIIOZZ5JqeqvYYIcEL727kskC66kF92t6Xl2V"
        crossorigin="anonymous"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    });
</script>

@if ($me->protectChanges ?? false)
<script>
    (function() {
        window.alxarafe_unsaved_changes = false;

        // Warn before leaving
        window.addEventListener('beforeunload', function(e) {
            if (window.alxarafe_unsaved_changes) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Mark as dirty on input/change
        document.addEventListener('input', function(e) {
            if (e.target.closest('form')) {
                window.alxarafe_unsaved_changes = true;
            }
        });
        document.addEventListener('change', function(e) {
            if (e.target.closest('form')) {
                window.alxarafe_unsaved_changes = true;
            }
        });

        // Reset on valid submit
        document.addEventListener('submit', function(e) {
            if (e.target.closest('form')) {
                window.alxarafe_unsaved_changes = false;
            }
        });
    })();
</script>
@endif

@php
    $githubUrl = \Alxarafe\Infrastructure\Persistence\Config::getConfig()->social->github ?? 'https://github.com/alxarafe/chascarrillo';
@endphp

<footer class="mt-auto py-5 border-top">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-center align-items-md-start">
            <div class="text-center text-md-start mb-3 mb-md-0">
                <p class="mb-0 text-muted small">
                    Powered by <a href="{{ $githubUrl }}" target="_blank" class="text-secondary fw-bold text-decoration-none">Chascarrillo</a>
                    <span class="ms-1 text-secondary-emphasis">{{ \Modules\Chascarrillo\Service\UpdateService::VERSION }}</span>.<br class="d-none d-md-block">
                    Developed with <strong>Alxarafe Framework</strong>
                </p>
            </div>
            <div class="text-center text-md-end">
                <p class="mb-0 text-muted small">
                    <i class="fas fa-shield-halved me-1"></i> No cookies, no tracking, no noise.<br class="d-none d-md-block">
                    Privacy by design.
                </p>
            </div>
        </div>
    </div>
</footer>

{!! $me->getRenderFooter() !!}

@stack('scripts')
