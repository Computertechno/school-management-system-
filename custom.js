/**
 * GAIMS - Custom JavaScript Utilities
 */

$(document).ready(function() {
    
    // ============================================
    // DYNAMIC FORM FIELD HANDLERS
    // ============================================
    
    // Add more fields dynamically
    $('.add-more').click(function() {
        var template = $(this).data('template');
        var container = $(this).data('container');
        var index = $(container + ' .dynamic-item').length;
        var newField = template.replace(/__index__/g, index);
        $(container).append(newField);
    });
    
    // Remove dynamic field
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.dynamic-item').remove();
    });
    
    // ============================================
    // CHARGE CALCULATION FOR GRADE ENTRY
    // ============================================
    window.calculateGrade = function(marks, maxMarks) {
        var percentage = (marks / maxMarks) * 100;
        var grade = '';
        
        if (percentage >= 80) grade = 'A';
        else if (percentage >= 70) grade = 'B';
        else if (percentage >= 60) grade = 'C';
        else if (percentage >= 50) grade = 'D';
        else if (percentage >= 40) grade = 'E';
        else grade = 'F';
        
        return { percentage: percentage.toFixed(1), grade: grade };
    };
    
    // ============================================
    // LIVE SEARCH
    // ============================================
    $('.live-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        var target = $(this).data('target');
        
        $(target + ' tr').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(searchTerm) === -1) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });
    
    // ============================================
    // AUTO-COMPLETE SEARCH
    // ============================================
    $('.autocomplete').on('input', function() {
        var searchTerm = $(this).val();
        var url = $(this).data('url');
        var target = $(this).data('target');
        
        if (searchTerm.length > 2) {
            $.ajax({
                url: url,
                type: 'GET',
                data: { search: searchTerm },
                success: function(data) {
                    $(target).html(data).show();
                }
            });
        } else {
            $(target).hide();
        }
    });
    
    // ============================================
    // PASSWORD STRENGTH METER
    // ============================================
    $('#password').on('keyup', function() {
        var password = $(this).val();
        var strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        var meter = $('#password-strength');
        var text = $('#password-strength-text');
        
        if (strength <= 2) {
            meter.css('width', '20%').removeClass('bg-success bg-warning').addClass('bg-danger');
            text.html('Weak').removeClass('text-success text-warning').addClass('text-danger');
        } else if (strength <= 4) {
            meter.css('width', '60%').removeClass('bg-danger bg-success').addClass('bg-warning');
            text.html('Medium').removeClass('text-danger text-success').addClass('text-warning');
        } else {
            meter.css('width', '100%').removeClass('bg-danger bg-warning').addClass('bg-success');
            text.html('Strong').removeClass('text-danger text-warning').addClass('text-success');
        }
    });
    
    // ============================================
    // CONFIRM PASSWORD VALIDATION
    // ============================================
    $('#confirm_password').on('keyup', function() {
        var password = $('#password').val();
        var confirm = $(this).val();
        
        if (password === confirm && password !== '') {
            $(this).removeClass('is-invalid').addClass('is-valid');
            $('#password-match').html('<i class="fas fa-check-circle text-success"></i> Passwords match').removeClass('text-danger').addClass('text-success');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
            $('#password-match').html('<i class="fas fa-times-circle text-danger"></i> Passwords do not match').removeClass('text-success').addClass('text-danger');
        }
    });
    
    // ============================================
    // CURRENCY FORMATTING
    // ============================================
    $('.currency').on('blur', function() {
        var value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(Number(value).toLocaleString());
        }
    });
    
    $('.currency').on('focus', function() {
        $(this).val($(this).val().replace(/,/g, ''));
    });
    
    // ============================================
    // DATE RANGE PICKER
    // ============================================
    $('#date-range').daterangepicker({
        opens: 'left',
        locale: {
            format: 'YYYY-MM-DD'
        }
    });
    
    // ============================================
    // CHART UPDATES ON FILTER CHANGE
    // ============================================
    $('.chart-filter').on('change', function() {
        var filterValue = $(this).val();
        var chartId = $(this).data('chart');
        var url = $(this).data('url');
        
        $.ajax({
            url: url,
            type: 'GET',
            data: { filter: filterValue },
            success: function(data) {
                // Update chart with new data
                if (window[chartId]) {
                    window[chartId].data.datasets[0].data = data.values;
                    window[chartId].update();
                }
            }
        });
    });
    
    // ============================================
    // REPORT GENERATION WITH LOADER
    // ============================================
    $('.generate-report').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        
        Swal.fire({
            title: 'Generating Report...',
            text: 'Please wait while we generate your report',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                window.location.href = url;
            }
        });
    });
});