/**
 * GAIMS - Gallery Functionality
 */

$(document).ready(function() {
    // Gallery filter functionality
    $('.filter-btn').click(function() {
        var category = $(this).data('filter');
        
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        if (category === 'all') {
            $('.gallery-item').fadeIn();
        } else {
            $('.gallery-item').each(function() {
                if ($(this).data('category') === category) {
                    $(this).fadeIn();
                } else {
                    $(this).fadeOut();
                }
            });
        }
    });
    
    // Lightbox functionality for gallery images
    $('.gallery-item img').click(function() {
        var imgSrc = $(this).attr('src');
        var imgTitle = $(this).attr('alt') || 'Gallery Image';
        
        Swal.fire({
            imageUrl: imgSrc,
            imageAlt: imgTitle,
            title: imgTitle,
            showCloseButton: true,
            showConfirmButton: false,
            width: '80%',
            imageWidth: '100%',
            imageHeight: 'auto'
        });
    });
});