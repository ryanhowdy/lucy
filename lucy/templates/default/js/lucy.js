//
// Global
// --------------------------------------------------
$(document).ready(function() {
});


//
// Tickets
// --------------------------------------------------
$('#ticket-details').ready(function() {
    // Status - quick edit
    $('#edit-status-menu > li > a').click(function () {
        event.preventDefault();

        var status_id = $(this).attr('id');
        status_id     = status_id.substring(7); // remove 'status_'

        $.ajax({
            url      : 'tickets.php',
            type     : 'POST',
            dataType : 'json',
            data     : {
                id        : $('#ticket-main').data('ticketId'),
                ajax      : 'edit',
                status_id : status_id
            }
        }).done(function(data) {
            if (data.status == 'success')
            {
                $('#status_label')
                    .css('background-color', '#' + data.data.changed.status_color)
                    .text(data.data.changed.status_name);

                if (data.data.current_user_got_points)
                {
                    displayModal(data.data.current_user_got_points.body, {
                        title : data.data.current_user_got_points.title,
                        type  : 'success'
                    });
                }
            }
            else if (data.status == 'error')
            {
                displayModal(data.message, { title: data.message, type: 'danger' });
            }
            else if (data.status == 'fail')
            {
                displayModal(data.data.errors, { title: data.data.title, type: 'danger' });
            }
        }).fail(function() {
            displayModal('An unknown error has occured.', { title: 'Uh-oh.', type: 'danger' });
        });
    });
    // Assignee - quick edit
    $('#edit-assignee-menu > li > a').click(function () {
        event.preventDefault();

        var assigned_id = $(this).attr('id');
        assigned_id     = assigned_id.substring(9); // remove 'assignee_'

        $.ajax({
            url      : 'tickets.php',
            type     : 'POST',
            dataType : 'json',
            data     : {
                id        : $('#ticket-main').data('ticketId'),
                ajax      : 'edit',
                assigned_id : assigned_id
            }
        }).done(function(data) {
            if (data.status == 'success')
            {
                if (data.data.changed.assigned_id == 'NONE')
                {
                    $('#assignee_label').html('<i>' + data.data.changed.assigned_name + '</i>');
                }
                else
                {
                    $('#assignee_label').html('<a href="user.php?id=' + data.data.changed.assigned_id + '">' + data.data.changed.assigned_name + '</a>');
                }
            }
            else if (data.status == 'error')
            {
                displayModal(data.message, { title: data.message, type: 'danger' });
            }
            else if (data.status == 'fail')
            {
                displayModal(data.data.errors, { title: data.data.title, type: 'danger' });
            }
        }).fail(function() {
            displayModal('An unknown error has occured.', { title: 'Uh-oh.', type: 'danger' });
        });
    });
    // Milestone - quick edit
    $('#edit-milestone-menu > li > a').click(function () {
        event.preventDefault();

        var milestone_id = $(this).attr('id');
        milestone_id     = milestone_id.substring(10); // remove 'milestone_'

        $.ajax({
            url      : 'tickets.php',
            type     : 'POST',
            dataType : 'json',
            data     : {
                id           : $('#ticket-main').data('ticketId'),
                ajax         : 'edit',
                milestone_id : milestone_id
            }
        }).done(function(data) {
            if (data.status == 'success')
            {
                if (data.data.changed.milestone_id == 'NONE')
                {
                    $('#milestone_label').html('<i>' + data.data.changed.milestone_name + '</i>');
                }
                else
                {
                    $('#milestone_label').html(
                        '<a href="milestones.php?milestone=' + data.data.changed.milestone_id + '">' + data.data.changed.milestone_name + '</a>'
                        + ' (' + data.data.changed.milestone_due + ')'
                    );
                }
            }
            else if (data.status == 'error')
            {
                displayModal(data.message, { title: data.message, type: 'danger' });
            }
            else if (data.status == 'fail')
            {
                displayModal(data.data.errors, { title: data.data.title, type: 'danger' });
            }
        }).fail(function() {
            displayModal('An unknown error has occured.', { title: 'Uh-oh.', type: 'danger' });
        });
    });
});

/**
 * displayModal 
 * 
 * @param title
 * @param body
 * @param footer
 * @param type
 * 
 * @return null
 */
function displayModal (body, args)
{
    var title  = args.title;
    var footer = args.footer;
    var type   = args.type;

    // Hide the footer, if not needed
    if (typeof footer === 'undefined')
    {
        $('#modal-window').find('.modal-footer').hide();
    }

    // Set the success/danger classes
    var contentClass = '';
    var headerClass  = '';

    if (type == 'success')
    {
        contentClass = 'panel-success';
        headerClass  = 'panel-heading';
    }
    else if (type == 'danger')
    {
        contentClass = 'panel-danger';
        headerClass  = 'panel-heading';
    }

    $('#modal-window').find('.modal-content').addClass(contentClass);
    $('#modal-window').find('.modal-header').addClass(headerClass);

    // Set the title
    $('#modal-window').find('.modal-title').text(title);

    // Set the body
    $('#modal-window').find('.modal-body').text(body);
    
    $('#modal-window').modal();
}
