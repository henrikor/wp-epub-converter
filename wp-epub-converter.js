jQuery(document).ready(function($) {
    var modal = $('#epubModal');
    var span = $('.close');

    $('.epub-link').click(function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        $('#epubPostId').val(postId);

        // Set default author
        $('#epubAuthor').val(wpEpubConverter.default_author);

        // Fetch post title and set default title
        $.get('/wp-json/wp/v2/posts/' + postId, function(post) {
            var title = post.title.rendered; // Use full title
            $('#epubTitle').val(title);
        });

        // Set default EPUB version to 3
        $('#epubVersion').val(3);

        modal.show();
    });

    span.click(function() {
        modal.hide();
    });

    $(window).click(function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });

    $('#epubForm').submit(function(e) {
        e.preventDefault();

        var data = {
            action: 'generate_epub',
            post_id: $('#epubPostId').val(),
            author: $('#epubAuthor').val(),
            title: $('#epubTitle').val(),
            version: $('#epubVersion').val(),
            kepub: $('#epubKepub').is(':checked') ? 1 : 0
        };

        console.log('Submitting form with data:', data);

        $.post(wpEpubConverter.ajax_url, data, function(response) {
            console.log('AJAX response:', response);

            if (response.success) {
                var downloadLink = '<a href="' + response.data.url + '">Download EPUB</a>';
                $('#epubForm').append('<div id="downloadLink">' + downloadLink + '</div>');
            } else {
                console.error('Failed to generate EPUB:', response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
        });
    });
});
