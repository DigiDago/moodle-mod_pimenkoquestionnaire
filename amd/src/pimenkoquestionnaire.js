define(['jquery'], function($) {
    let event = function event() {
        $('.printtopdf').click(function () {
            let title = $('#coursetitle').val();
            document.title = window.parent.document.title = title;

            // Other solution.
            // html2canvas(document.getElementById("region-main")).then(canvas => {
            //     let pdf = new jsPDF('p', 'mm', 'a4');
            //     pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, 211, 298);
            //     pdf.save('Test.pdf');
            // });
        });
    };

    return {
        init: function() {
            event();
        }
    };
});