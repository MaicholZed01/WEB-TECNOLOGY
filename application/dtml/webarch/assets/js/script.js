        // Set active nav link based on current page
        $(document).ready(function() {
            var currentPage = '<[current_page]>';
            $('.nav-link').removeClass('active');
            $('.nav-link[data-page="' + currentPage + '"]').addClass('active');
            
            // Mobile menu toggle
            $('#sidebarToggle').on('click', function() {
                $('#sidebar').toggleClass('active');
                $('#mobileOverlay').toggleClass('active');
            });
            
            $('#mobileOverlay').on('click', function() {
                $('#sidebar').removeClass('active');
                $(this).removeClass('active');
            });
        });