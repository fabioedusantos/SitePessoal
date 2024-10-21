$(document).ready(function() {
    let currentIndex = 0;
    const items = $('#palestras-apresentacoes .carousel-item');
    const itemCount = items.length;

    function showItem(index) {
        const newTranslateX = -index * 100 + '%';
        $('#palestras-apresentacoes .carousel').css('transform', 'translateX(' + newTranslateX + ')');
    }

    $('#palestras-apresentacoes .next-btn').click(function() {
        currentIndex = (currentIndex + 1) % itemCount;
        showItem(currentIndex);
    });

    $('#palestras-apresentacoes .prev-btn').click(function() {
        currentIndex = (currentIndex - 1 + itemCount) % itemCount;
        showItem(currentIndex);
    });
});