var $ = jQuery;
var $list;

function initList() {
    $list.sortable({
        update: function() {

            var r = [];
            $list.find('li').each(function(index){
                r.push({
                    post_id: $(this).find('[name=post_id]').val(),
                    collection: $(this).find('[name=collection]').val()
                })
            });

            $.post(postscollections.ajax_url, {
                action: 'postscollectionssaveorder',
                order: r
            })
        }
    });
}

function setEvents() {
    $list.on('click', '.postscollections__remove', function(ev){
        ev.preventDefault();

        var $li = $(this).parents('li');
        $.post(postscollections.ajax_url, {
            action: 'postscollectionsremove',
            item: {
                post_id: $li.find('[name=post_id]').val(),
                collection: $li.find('[name=collection]').val()
            }
        }, function(){
            window.location = window.location;
        });
        
    });
}

jQuery(function(){
    $list = $('ol.postscollections__posts');

    initList();
    setEvents();
});