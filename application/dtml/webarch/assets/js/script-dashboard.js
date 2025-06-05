
$(document).ready(function() {
    // Anima i numeri delle statistiche
    $('.animate-number').each(function() {
        var $this = $(this);
        var endValue = $this.attr('data-value');
        $({ value: 0 }).animate({ value: endValue }, {
            duration: 1200,
            easing: 'swing',
            step: function() {
                $this.text(Math.floor(this.value));
            },
            complete: function() {
                $this.text(endValue);
            }
        });
    });
});
