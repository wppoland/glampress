jQuery(document).ready(function ($) {
    'use strict';

    // Funkcjonalność sortowania drag-and-drop
    var galleryPreview = $('#crg-gallery-container .crg-gallery-preview');

    galleryPreview.sortable({
        placeholder: 'ui-state-highlight',
        stop: function () {
            updateImageIds();
        }
    });

    // Funkcja do aktualizacji ukrytego pola z ID obrazów
    function updateImageIds() {
        var imageIds = [];
        galleryPreview.find('li').each(function () {
            var id = $(this).data('id');
            if (id) {
                imageIds.push(id);
            }
        });
        $('#crg_image_ids').val(imageIds.join(','));
    }

    // Funkcjonalność otwierania biblioteki mediów
    var mediaFrame;

    $('#crg-add-edit-gallery-button').on('click', function (e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Wybierz lub wgraj obrazy do Twojej Galerii',
            button: {
                text: 'Zaktualizuj Galerię'
            },
            library: {
                type: 'image'
            },
            multiple: 'add'
        });

        mediaFrame.on('select', function () {
            var selection = mediaFrame.state().get('selection');
            var imageIds = [];
            var previewHtml = '';

            selection.each(function (attachment) {
                var image = attachment.toJSON();
                imageIds.push(image.id);
                previewHtml += '<li data-id="' + image.id + '"><img src="' + image.sizes.thumbnail.url + '" alt=""></li>';
            });

            galleryPreview.html(previewHtml);
            updateImageIds();
        });

        mediaFrame.open();
    });
});