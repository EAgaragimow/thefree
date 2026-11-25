import Swiper from 'swiper';
import 'swiper/css';

/**
 * A slider with one full-width slide in view that moves one slide at a time and whose height
 * follows the slide's content (see the highlight gallery). `element` is the `.swiper` element;
 * the instance is kept on `element.swiper`.
 */
window.gallerySlider = (element) => new Swiper(element, {
    slidesPerView: 1,
    autoHeight: true,
    grabCursor: true,
    observer: true,
    observeParents: true,
});
