
        $(document).ready(function() {
            // Animazione numeri statistiche
            $('.animate-number').each(function() {
                const $this = $(this);
                const targetValue = parseInt($this.data('value')) || 0;
                let currentValue = 0;
                const increment = Math.ceil(targetValue / 50);
                
                const timer = setInterval(function() {
                    currentValue += increment;
                    if (currentValue >= targetValue) {
                        currentValue = targetValue;
                        clearInterval(timer);
                    }
                    $this.text(currentValue);
                }, 30);
            });

            // Filtri
            $('.filter-group select, .filter-group input').on('change', function() {
                // Logica di filtro - da implementare lato server
                console.log('Filtro applicato:', $(this).val());
            });

            // Conferma eliminazione
            $('[data-action="delete"]').on('click', function(e) {
                if (!confirm('Sei sicuro di voler eliminare questo appuntamento?')) {
                    e.preventDefault();
                }
            });
        });
