jQuery(document).ready(function($) {
    function initializeTabs() {
        $('.wpcc-section').hide();
        var urlParams = new URLSearchParams(window.location.search);
        var activeTab = urlParams.get('tab') || 'basic';
        
        $('.wpcc-tab').removeClass('active');
        $('.wpcc-tab[data-tab="' + activeTab + '"]').addClass('active');
        $('#wpcc-section-' + activeTab).show();
    }
    
    initializeTabs();
    
    $('.wpcc-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        $('.wpcc-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.wpcc-section').hide();
        $('#wpcc-section-' + targetTab).show();
        
        var url = new URL(window.location);
        url.searchParams.set('tab', targetTab);
        window.history.replaceState({}, '', url);
    });
    
    $('.wpcc-switch input').on('change', function() {
        var $switch = $(this);
        var $dependent = $('[data-depends="' + $switch.attr('name') + '"]');
        
        if ($switch.is(':checked')) {
            $dependent.show();
        } else {
            $dependent.hide();
        }
    });
    
    $('select[name="wpcc_translate_type"]').on('change', function() {
        var value = $(this).val();
        var $dependent = $('.translate-type-dependent');
        
        if (value == '1') {
            $dependent.show();
        } else {
            $dependent.hide();
        }
    });
    
    $('select[name="wpcc_browser_redirect"]').on('change', function() {
        var value = $(this).val();
        var $dependent = $('.browser-redirect-dependent');
        
        if (value != '0') {
            $dependent.show();
        } else {
            $dependent.hide();
        }
    });
    
    $('select[name="wpcc_use_cookie_variant"]').on('change', function() {
        var value = $(this).val();
        var $dependent = $('.cookie-variant-dependent');
        
        if (value != '0') {
            $dependent.show();
        } else {
            $dependent.hide();
        }
    });
    
    $('.wpcc-language-item input[type="checkbox"]').on('change', function() {
        var $checkbox = $(this);
        var $textInput = $checkbox.closest('.wpcc-language-item').find('input[type="text"]');
        
        if ($checkbox.is(':checked')) {
            $textInput.prop('disabled', false);
        } else {
            $textInput.prop('disabled', true);
        }
    });
    
    $('.wpcc-language-item input[type="checkbox"]').trigger('change');
    
    $('form').on('submit', function() {
        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');
        
        $submitBtn.prop('disabled', true).val('保存中...');
        
        setTimeout(function() {
            $submitBtn.prop('disabled', false).val('保存选项');
        }, 2000);
    });
    
    $('.wpcc-tooltip').on('mouseenter', function() {
        $(this).find('.tooltiptext').stop().fadeIn(200);
    }).on('mouseleave', function() {
        $(this).find('.tooltiptext').stop().fadeOut(200);
    });
    
    var hash = window.location.hash;
    if (hash) {
        var tab = hash.replace('#', '');
        var $tab = $('.wpcc-tab[data-tab="' + tab + '"]');
        if ($tab.length) {
            $tab.trigger('click');
        }
    }
    

});