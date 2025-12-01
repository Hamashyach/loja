document.addEventListener('DOMContentLoaded', function(){
     //carossel produtos

     const track = document.querySelector('.carousel-track2');
    const prevButton = document.querySelector('.carousel-button2.prev');
    const nextButton = document.querySelector('.carousel-button2.next');

    
    if (!track) return;

    
    const updateButtons = () => {
        const canScrollLeft = track.scrollLeft > 0;
        const canScrollRight = track.scrollWidth > track.clientWidth + track.scrollLeft;

        prevButton.disabled = !canScrollLeft;
        nextButton.disabled = !canScrollRight;
    };

    
    nextButton.addEventListener('click', () => {
        const slideWidth = track.querySelector('.carousel-slide2').offsetWidth;
        track.scrollBy({ left: slideWidth + 30, behavior: 'smooth' }); 
    });

    prevButton.addEventListener('click', () => {
        const slideWidth = track.querySelector('.carousel-slide2').offsetWidth;
        track.scrollBy({ left: -(slideWidth + 30), behavior: 'smooth' }); 
    });

    
    track.addEventListener('scroll', updateButtons);

    
    updateButtons();
});