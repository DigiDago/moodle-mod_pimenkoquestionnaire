define(['jquery'], function($) {
    let event = function event() {
        let title = $('#coursetitle').val();
        document.title = window.parent.document.title = title;
    };

    return {
        init: function() {
            event();
        }
    };
});