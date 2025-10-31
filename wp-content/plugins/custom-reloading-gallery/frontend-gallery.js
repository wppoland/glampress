document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const galleries = document.querySelectorAll('.crg-gallery-wrapper');

    galleries.forEach(gallery => {
        const imageData = JSON.parse(gallery.dataset.images);
        if (!imageData || imageData.length === 0) return;

        let currentIndex = parseInt(gallery.dataset.initialIndex, 10);
        const baseUrl = gallery.dataset.baseUrl;
        const totalImages = imageData.length;

        const mainImage = gallery.querySelector('.crg-main-image-wrapper img');
        const counter = gallery.querySelector('.crg-counter');
        const prevLink = gallery.querySelector('.crg-thumb-prev');
        const nextLink = gallery.querySelector('.crg-thumb-next');
        const prevThumb = prevLink.querySelector('img');
        const nextThumb = nextLink.querySelector('img');
        
        function updateGallery(newIndex, pushState = true) {
            mainImage.classList.add('crg-loading');
            mainImage.src = imageData[newIndex].large;
            mainImage.onload = () => mainImage.classList.remove('crg-loading');

            counter.textContent = `${newIndex + 1} / ${totalImages}`;

            const prevIndex = (newIndex > 0) ? newIndex - 1 : totalImages - 1;
            const nextIndex = (newIndex < totalImages - 1) ? newIndex + 1 : 0;

            prevThumb.src = imageData[prevIndex].thumb;
            prevLink.dataset.index = prevIndex;

            nextThumb.src = imageData[nextIndex].thumb;
            nextLink.dataset.index = nextIndex;

            if (pushState) {
                const newUrl = `${baseUrl}?image_index=${newIndex}`;
                const state = { galleryId: gallery.id, imageIndex: newIndex };
                history.pushState(state, '', newUrl + `#${gallery.id}`);
            }
            currentIndex = newIndex;
        }

        gallery.addEventListener('click', function (e) {
            const target = e.target.closest('a[data-index]');
            if (!target) return;
            
            e.preventDefault();
            const newIndex = parseInt(target.dataset.index, 10);
            if (newIndex !== currentIndex) {
                updateGallery(newIndex);
            }
        });

        window.addEventListener('popstate', function (e) {
            if (e.state && e.state.galleryId === gallery.id) {
                updateGallery(e.state.imageIndex, false);
            } else {
                const params = new URLSearchParams(window.location.search);
                const indexFromUrl = parseInt(params.get('image_index') || '0', 10);
                if (indexFromUrl !== currentIndex) {
                     updateGallery(indexFromUrl, false);
                }
            }
        });
    });
});