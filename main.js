/**
 * GAIMS - Main JavaScript File
 * Greenhill Academy Integrated Management System
 */

// Wait for DOM to be fully loaded
$(document).ready(function() {
    
    // ============================================
    // BACK TO TOP BUTTON
    // ============================================
    $(window).scroll(function() {
        if ($(this).scrollTop() > 300) {
            $('#backToTop').fadeIn();
        } else {
            $('#backToTop').fadeOut();
        }
    });
    
    $('#backToTop').click(function(e) {
        e.preventDefault();
        $('html, body').animate({scrollTop: 0}, 500);
    });
    
    // ============================================
    // SIDEBAR TOGGLE FOR MOBILE
    // ============================================
    $('.mobile-toggle').click(function() {
        $('.sidebar').toggleClass('active');
    });
    
    // ============================================
    // FORM VALIDATION
    // ============================================
    $('form').on('submit', function(e) {
        var requiredFields = $(this).find('[required]');
        var isValid = true;
        
        requiredFields.each(function() {
            if ($(this).val() === '' || $(this).val() === null) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showToast('Please fill all required fields', 'error');
        }
    });
    
    // Remove invalid class on input
    $('input, select, textarea').on('input change', function() {
        if ($(this).val() !== '') {
            $(this).removeClass('is-invalid');
        }
    });
    
    // ============================================
    // CONFIRM DELETE MODAL
    // ============================================
    window.confirmDelete = function(entityName, entityId, deleteUrl) {
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${entityName}. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    };
    
    // ============================================
    // SUCCESS/ERROR TOAST NOTIFICATIONS
    // ============================================
    window.showToast = function(message, type = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    };
    
    // ============================================
    // AUTO-HIDE ALERTS AFTER 5 SECONDS
    // ============================================
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // ============================================
    // DATA TABLE SEARCH FUNCTIONALITY
    // ============================================
    $('.search-table').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        var tableId = $(this).data('table');
        $('#' + tableId + ' tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // ============================================
    // SELECT ALL CHECKBOXES
    // ============================================
    $('.select-all').on('change', function() {
        var isChecked = $(this).prop('checked');
        $(this).closest('table').find('.select-item').prop('checked', isChecked);
    });
    
    // ============================================
    // TOOLTIP INITIALIZATION
    // ============================================
    $('[data-toggle="tooltip"]').tooltip();
    
    // ============================================
    // POPOVER INITIALIZATION
    // ============================================
    $('[data-toggle="popover"]').popover();
    
    // ============================================
    // DATE PICKER FOR FORMS
    // ============================================
    $('.datepicker').attr('type', 'date');
    
    // ============================================
    // NUMBER INPUT VALIDATION
    // ============================================
    $('input[type="number"]').on('keypress', function(e) {
        var charCode = (e.which) ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode !== 46) {
            e.preventDefault();
        }
    });
    
    // ============================================
    // PHONE NUMBER FORMATTING
    // ============================================
    $('.phone-input').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length > 0 && value[0] !== '0') {
            value = '0' + value;
        }
        $(this).val(value.substring(0, 10));
    });
    
    // ============================================
    // AUTO-GENERATE USERNAME FROM NAME
    // ============================================
    $('.auto-username').on('input', function() {
        var firstName = $('#first_name').val();
        var lastName = $('#last_name').val();
        if (firstName && lastName) {
            var username = firstName.toLowerCase() + '.' + lastName.toLowerCase();
            $('.username-field').val(username);
        }
    });
    
    // ============================================
    // PRINT FUNCTION
    // ============================================
    window.printPage = function(elementId) {
        var printContents = document.getElementById(elementId).innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    };
    
    // ============================================
    // COPY TO CLIPBOARD
    // ============================================
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard!', 'success');
        }, function() {
            showToast('Failed to copy', 'error');
        });
    };
    
    // ============================================
    // EXPORT TABLE TO CSV
    // ============================================
    window.exportTableToCSV = function(tableId, filename) {
        var csv = [];
        var rows = document.querySelectorAll('#' + tableId + ' tr');
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (var j = 0; j < cols.length; j++) {
                var text = cols[j].innerText.replace(/,/g, '');
                row.push('"' + text + '"');
            }
            csv.push(row.join(','));
        }
        
        var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
        var downloadLink = document.createElement('a');
        downloadLink.download = filename + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    };
    
    // ============================================
    // LOADING SPINNER
    // ============================================
    window.showLoading = function() {
        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    };
    
    window.hideLoading = function() {
        Swal.close();
    };
    
    // ============================================
    // AJAX FORM SUBMISSION
    // ============================================
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action');
        var data = form.serialize();
        
        showLoading();
        
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast(response.message, 'success');
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1500);
                    } else if (response.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                hideLoading();
                showToast('An error occurred. Please try again.', 'error');
            }
        });
    });
});