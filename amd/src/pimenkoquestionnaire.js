define(['jquery'], function($) {
    let event = function event() {
        $('.printtopdf').click(function () {
            html2canvas(document.body).then(canvas => {
                let pdf = new jsPDF('p', 'mm', 'a4');
                pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, 211, 298);
                pdf.save('Test.pdf');
            });
        });
    };

    return {
        init: function() {
            event();
        }
    };
});