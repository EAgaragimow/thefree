import Swiper from 'swiper';
import { FreeMode } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/free-mode';

/**
 * A free-scrolling row of slides that are as wide as their content (see the highlight gallery).
 * `$el` is the `.swiper` element; the instance is kept on `$el.swiper`.
 */
window.freeSlider = (element) => new Swiper(element, {
    modules: [FreeMode],
    slidesPerView: 'auto',
    spaceBetween: 12,
    freeMode: true,
    grabCursor: true,
    observer: true,
    observeParents: true,
});
