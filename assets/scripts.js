jQuery(document).ready(function( $ ) {
    /**
     * Add subject parameter to form action
     * Required to filter result based on subject
    */ 
    $('#filter-button').on('click', function(e) {
        e.preventDefault();
        const $this = $(this);
        const form = $this.closest('form');
        let formAction = form.prop('action');
        const formSubject = $this.siblings().val();
        const paramIndex = formAction.indexOf( '&subject=' );
        if( paramIndex > -1 ){
            formAction = formAction.substring(0, paramIndex);
        }
        if( formSubject ){
            form.attr('action', formAction + '&subject=' + formSubject );
        } else {
            form.attr('action', formAction );
        }
        form.submit();
    });
    
    $('.list-action').on('click', function(){
        let $this = $(this);
        // Prevent double click
        if ( $this.data('disable') ) {
            return false;
        }
        $this.data('disable', true);
        let parent = $this.closest('div');
        let container = $this.closest('.wrap');
        let popup = container.find('.email-details');
        let item = $this.closest('tr');
	    let todo = $this.data('action');
        if ( todo === 'delete' ) {
            if( ! confirm( "Hmm... Do you really want to delete this?" ) ) {
                $this.data('disable', false);
                return false;
            }
            item.fadeOut();
        } else {
            let html = '<div class="loader-grey"><div class="loader"></div></div>';
            container.append(html);
            $('body').addClass('popup-active');
        }
        $.ajax({
	        url : ajaxurl,
	        type : 'post',
	        data : {
		        action  : 'v_email_list',
		        nonce   : parent.data('nonce'),
		        id      : parent.data('id'),
		        todo    : todo
	        },
		    success : function( response ) {
		        $this.data('disable', false);
		        if ( response.data.delete ) {
		            item.remove();
		        } else if ( typeof response.data.view !== 'undefined' ) {
		            container.find('.loader-grey').remove();
		            popup.find('.content').remove();
		            popup.find('.popup-body').append('<div class="content">'+response.data.view+'</div>');
		            popup.addClass('show');
		        } else {
		            alert(response.data);
		        }
		    }
	    });
	    return false;
    });

    // Close popup
    $('.popup-close').on('click', function(){
        $(this).closest('.popup-overlay').removeClass('show');
        $('body').removeClass('popup-active');
    });
    
    // Prevent propagation
    $('.popup-body').on('click', function(e){
        e.stopPropagation();
    });

    // Change UTC to local time
    $(".local-time").each(function() {
        const date = $(this).text().replace(' ', 'T') + '.000Z';
        $(this).text( new Intl.DateTimeFormat([], {dateStyle: 'full', timeStyle: 'short'}).format(new Date(date)) );
    });

    // Show confirmation popup
    $(".confirm-action").on("click", function(e) {
        if( ! confirm( "Are you sure you want to proceed?" ) ) {
            e.preventDefault();
        }
    });
});