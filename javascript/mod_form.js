jQuery(document).ready(function () {
    // This sets the min presence time to half the duration as default!
    $('#id_duration,#id_maxduration').on('change', function () {
        var duration = $(this).val();
        $("#id_presence").val(Math.round(duration / 2));
        $("#id_minpresence").val(Math.round(duration / 2));
    });

    // This is to add a div around the buttons for display purposes!
    // This is for both Via and Viaassign!
    var participants = $("input[id ^= 'id_participants']");
    if (participants.length == 0) { participants = $("button[id ^= 'id_participants']"); }

    $(participants).each(function (i, val) {

        // Add class to both inputs.
        $(val).addClass("btn_participants")
    });
    $(".btn_participants").wrapAll("<div class='btns' />");

    // This is to add a div around the buttons for display purposes!
    var animators = $("input[id ^= 'id_animators']");
    if (animators.length == 0) { animators = $("button[id ^= 'id_animators']"); }
    $(animators).each(function (i, val) {
        // Add class to both inputs.
        $(val).addClass("btn_animators")
    });
    $(".btn_animators").wrapAll("<div class='btns' />");

    if ($('#id_enroltype').val() == 0 && $('[name="wassaved"]').val() == 0) {

        // Automatic enrol and not saved we hide all the user lists.
        $('#fgroup_id_add_hostgroup').addClass('hide');
        $('#fgroup_id_add_users').addClass('hide');
        if ($("div[data-groupname='add_users']").length > 0) {
            $("div[data-groupname='add_users']").addClass('hide');
        }
        if ($("div[data-groupname='add_hostgroup']").length > 0) {
            $("div[data-groupname='add_hostgroup']").addClass('hide');
        }
        $('.viausers').addClass('hide');
        if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
            $('#fitem_id_searchpotentialusers').addClass('hide');
            $('#fitem_id_searchparticipants').addClass('hide');
        } else {
            $('#id_searchpotentialusers').closest(".search").addClass('hide');
            $('#id_searchparticipants').closest(".search").addClass('hide');
        }

    } else if ($('#id_enroltype').val() == 0 && $('[name="wassaved"]').val() == 1) {
        // Automatic enrol and was saved we add all the potential users to the participants list.
        var potentialusersselect = $(".viauserlists:not(.hide):first").attr("id");
        $("#" + potentialusersselect + " option").each(function () {
            $(this).remove().appendTo('#id_participants');
        });

        $(".viauserlists:not(.hide):first").addClass('hide');
        $('#id_participants_remove_btn').addClass('hide');
        $('#id_participants_add_btn').addClass('hide');
        // We hide the title too!
        $('.three.potentialusers').addClass('hide');
        $('.three.participants').addClass('element');
        if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
            $('#fitem_id_searchpotentialusers').addClass('hide');
        } else {
            $('#id_searchpotentialusers').closest(".search").addClass('hide');
        }
    }

    $('#id_searchparticipants').keyup(function () {
        filterParticipantsList('#id_searchparticipants', '#id_participants option');
    });

    $('#id_searchpotentialusers').keyup(function () {
        var potentialusersselect = $(".viauserlists:not(.hide):first").attr("id");

        filterParticipantsList('#id_searchpotentialusers', '#' + potentialusersselect + ' option');
    });

    $('#id_enroltype').change(function () {
        if ($('[name="wassaved"]').val() == 0) {
            // Activity was not yet saved.
            if ($('#id_enroltype').val() == 0) {
                // Automatic enrol!
                $('#fgroup_id_add_hostgroup').addClass('hide');
                $('#fgroup_id_add_users').addClass('hide');
                $('.viausers').addClass('hide');
                // No user lists are displayed and therefor there are no seaches.
                if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
                    $('#fitem_id_searchpotentialusers').addClass('hide');
                    $('#fitem_id_searchparticipants').addClass('hide');
                } else {
                    $('#id_searchpotentialusers').closest(".search").addClass('hide');
                    $('#id_searchparticipants').closest(".search").addClass('hide');
                }

                if ($("div[data-groupname='add_users']").length > 0) {
                    $("div[data-groupname='add_users']").addClass('hide');
                }
                if ($("div[data-groupname='add_hostgroup']").length > 0) {
                    $("div[data-groupname='add_hostgroup']").addClass('hide');
                }
            } else {
                // Manual enrol!
                $('#fgroup_id_add_hostgroup').removeClass('hide');
                $('#fgroup_id_add_users').removeClass('hide');
                $('.viausers').removeClass('hide');
                // We can search for in both lists.
                $('#id_participants').removeClass('hide');
                if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
                    $('#fitem_id_searchparticipants').removeClass('hide');
                    $('#fitem_id_searchpotentialusers').removeClass('hide');
                } else {
                    $('#id_searchparticipants').closest(".search").removeClass('hide');
                    $('#id_searchpotentialusers').closest(".search").removeClass('hide');
                }

                if ($("div[data-groupname='add_users']").length > 0) {
                    $("div[data-groupname='add_users']").removeClass('hide');
                }
                if ($("div[data-groupname='add_hostgroup']").length > 0) {
                    $("div[data-groupname='add_hostgroup']").removeClass('hide');
                }

                $('.viauserlists.participants').removeClass('hide');
                $('#id_animators_remove_btn').removeClass('hide');
                $('#id_animators_add_btn').removeClass('hide');
                // We show the title too!
                $('.three.participants').removeClass('hide');
            }
        } else {
            // Activity was already saved, we are editing!
            if ($('#id_enroltype').val() == 0) {
                // Automatic enrol!

                // Move all the potential users to the participants list...
                var potentialusersselect = $(".viauserlists:not(.hide):first").attr("id");
                $("#" + potentialusersselect + " option").each(function () {
                    $(this).remove().appendTo('#id_participants');
                });
                // then hide the lists
                $(".viauserlists:not(.hide):first").addClass('hide');
                $('#id_participants_remove_btn').addClass('hide');
                $('#id_participants_add_btn').addClass('hide');
                // We hide the title too!
                $('.three.potentialusers').addClass('hide');
                $('.three.participants').addClass('element');
                // We can search for participants but not in the potential users' list!
                if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
                    $('#fitem_id_searchpotentialusers').addClass('hide');
                    $('#fitem_id_searchparticipants').removeClass('hide');
                } else {
                    $('#id_searchpotentialusers').closest(".search").addClass('hide');
                    $('#id_searchparticipants').closest(".search").removeClass('hide');
                }

            } else {
                // Manual enrol!
                $('.potentialusers').removeClass('hide');
                $('#id_participants_remove_btn').removeClass('hide');
                $('#id_participants_add_btn').removeClass('hide');
                // We hide the title too!
                $('.three.potentialusers').removeClass('hide');
                $('.three.participants').removeClass('element');
                // We can search for in both lists!
                if ($('#fitem_id_searchpotentialusers').eq(0).length > 0) {
                    $('#fitem_id_searchpotentialusers').removeClass('hide');
                    $('#fitem_id_searchparticipants').removeClass('hide');
                } else {
                    $('#id_searchpotentialusers').closest(".search").removeClass('hide');
                    $('#id_searchparticipants').closest(".search").removeClass('hide');
                }
            }
        }
    });

    $('#id_noparticipants').click(function () {
        if ($('#id_enroltype').val() == 0) {
            if ($('#id_noparticipants').is(':checked')) {
                // Move all participants to animators!
                $("#id_participants option").each(function () {
                    $(this).remove().appendTo('#id_animators');
                    $(this).prop("selected", false);
                });

                // Hide the whole participants list.
                $('.viauserlists.participants').addClass('hide');
                $('#id_animators_remove_btn').addClass('hide');
                $('#id_animators_add_btn').addClass('hide');
                // We hide the title too!
                $('.three.participants').addClass('hide');
            } else {
                // show the whole participants list!
                $('.viauserlists.participants').removeClass('hide');
                $('#id_animators_remove_btn').removeClass('hide');
                $('#id_animators_add_btn').removeClass('hide');
                // We show the title too!
                $('.three.participants').removeClass('hide');
            }
        }
    });

    // Grouping mode selected; we need to reload with only the users of the group!
    $('#id_groupingid').change(function () {
        $(".fa-spinner.fa-spin").show();
        $("#id_submitbutton").attr('disabled', 'disabled');
        $("#id_submitbutton2").attr('disabled', 'disabled');
        $("#id_participants").empty();
        $("#id_animators").empty();

        var groupingid = this.value;
        var enroltype = $('#id_enroltype option:selected').val();
        var link = window.location.href;

        $.ajax({
            type: "POST",
            url: link,
            data: {
                groupingid: groupingid,
                enroltype: enroltype
            },
            success: function (html) {
                var list = $(html).find('#id_potentialusers option');

                if (enroltype == "0") {
                    $("#id_participants").html(list);
                } else {
                    $("#id_potentialusers").html(list);
                }
                var host = $(html).find('#id_host option');

                $("#id_host").html(host);
                $(".fa-spinner.fa-spin").hide();
                $("#id_submitbutton").removeAttr('disabled')
                $("#id_submitbutton2").removeAttr('disabled')
            }
        });
    });

    $('#id_submitbutton, #id_submitbutton2').click(function () {
        createUserList();
    });

    function createUserList() {
        var participants = '';
        var animators = '';
        var totalp = 0;
        var totala = 0;
        var count = 1;

        totalp = $('#id_participants option').length;
        totala = $("#id_animators option").length;

        $("#id_participants option").each(function () {
            participants += $(this).val();
            if (count < totalp) {
                participants += ', ';
            }
            count += 1;
        });

        count = 1;
        $("#id_animators option").each(function () {
            animators += $(this).val();
            if (count < totala) {
                animators += ', ';
            }
            count += 1;
        });

        var host = $("#id_host option").val();

        $("#id_save_participants:text").val(participants);
        $("#id_save_animators:text").val(animators);
        $("#id_save_host:text").val(host);
    }

    setgroupfunction();

    // Edit participants clicked.
    if (window.location.href.indexOf("#id_enrolmentheader") != -1) {
        setTimeout(function () { $("a[aria-controls='id_enrolmentheader']")[0].click(); }, 1000);
    }
});

function replace_host() {
    // Only one can be picked.
    var selected = $(".viauserlists :selected").length;

    if (selected != 0) {
        if (selected > 1) {
            $(".viauserlists option:selected").each(function () {
                $(this).prop("selected", false);
            });
        } else {
            // Remove the actual host.
            $("#id_host option").each(function () {
                $(this).remove().appendTo('#id_animators');
                $(this).prop("selected", false);
            });
            $("#host option:selected").removeAttr("selected");
            // Add the new host; it is only possible to add 1.
            if ($('#id_host option').length === 0) {
                $('.viauserlists option:selected').remove().appendTo('#id_host');
                $('.viauserlists option:selected').removeAttr("selected");
            }
        }
    }
}

function add_participants() {
    var potentialusersselect = $(".viauserlists:not(.hide):first select").attr("id");
    if (!potentialusersselect) { potentialusersselect = $(".viauserlists:not(.hide):first").attr("id"); }

    $("#" + potentialusersselect + " option:selected").each(function () {
        $(this).remove().appendTo('#id_participants');
        $(this).prop("selected", false);
    });

    $("#" + potentialusersselect + " option").each(function () {
        if ($(this).parent().is('span')) {
            $(this).unwrap().show();
        }
    });
    $('#id_searchpotentialusers:text').val('');
}

function remove_participants() {
    var potentialusersselect = $(".viauserlists:not(.hide):first select").attr("id");
    if (!potentialusersselect) { potentialusersselect = $(".viauserlists:not(.hide):first").attr("id"); }
    $("#id_participants option:selected").each(function () {
        $(this).remove().appendTo('#' + potentialusersselect);
        $(this).prop("selected", false);
    });

    $("#id_participants option").each(function () {
        if ($(this).parent().is('span')) {
            $(this).unwrap().show();
        }
    });
    $('#id_searchparticipants:text').val('');
}

function add_animators() {
    $("#id_participants option:selected").each(function () {
        $(this).remove().appendTo('#id_animators');
        $(this).prop("selected", false);
    });

    $("#id_participants option").each(function () {
        if ($(this).parent().is('span')) {
            $(this).unwrap().show();
        }
    });
    $('#id_searchparticipants:text').val('');
}

function remove_animators() {
    $("#id_animators option:selected").each(function () {
        $(this).remove().appendTo('#id_participants');
        $(this).prop("selected", false);
    });
}

function setgroupfunction() {
    // There is probably a better way to improve this function.
    var Inter = setInterval(function () {
        if ($('.availability_grouping .availability-group select[name="id"]').length) {
            $('.availability_grouping .availability-group select[name="id"]').attr('onchange', 'groupuserschange()');
        }
        if ($('.availability_group .availability-group select[name="id"]').length) {
            $('.availability_group .availability-group select[name="id"]').attr('onchange', 'groupuserschange()');
        }

        if ($(".availability-item").find(".availability-delete")) {
            $(".availability-item").find(".availability-delete").each(function () {
                if ($(this).parent().find('.availability_grouping .availability-group select[name="id"]').length > 0) {
                    if (!$._data($(this)[0], "events")) {
                        $(this).click(function (e) {
                            groupuserschange();
                        });
                    }
                }
                if ($(this).parent().find('.availability_group .availability-group select[name="id"]').length > 0) {

                    if (!$._data($(this)[0], "events")) {
                        $(this).click(function (e) {
                            groupuserschange();
                        });
                    }
                }
            });
        }


    }, 1000);
}

function groupuserschange() {

    $(".fa-spinner.fa-spin").show();
    $("#id_submitbutton").attr('disabled', 'disabled');
    $("#id_submitbutton2").attr('disabled', 'disabled');
    $("#id_participants").empty();
    $("#id_animators").empty();
    /*if ($("#availability_addrestriction_group")[0])
        $("#availability_addrestriction_group")[0].disabled = true;
    if ($(".availability-header"))
    $(".availability-header").hide()*/

    var groupingid = 0;
    var groupid = 0;

    if ($('.availability_grouping .availability-group select[name="id"]')) { groupingid = $('.availability_grouping .availability-group select[name="id"]').val(); }
    if ($('.availability_group .availability-group select[name="id"]')) { groupid = $('.availability_group .availability-group select[name="id"]').val(); }

    var enroltype = $('#id_enroltype option:selected').val();

    var link = window.location.href;
    $.ajax({
        type: "POST",
        url: link,
        data: {
            groupingid: groupingid,
            enroltype: enroltype,
            groupid: groupid,
            fromjs: true
        },
        success: function (html) {

            var list = $(html).find('#id_potentialusers option');

            if (enroltype == "0") {
                $("#id_participants").html(list);
            } else {
                $("#id_potentialusers").html(list);
            }

            var host = $(html).find('#id_host option');
            $("#id_host").html(host);
            $("#id_submitbutton").removeAttr('disabled')
            $("#id_submitbutton2").removeAttr('disabled')
            $(".fa-spinner.fa-spin").hide();
        }
    });
}

function filterParticipantsList(search, select) {
    var textbox = $(search).val();

    $(select).each(function (index, val) {
        if (val.text.toLowerCase().indexOf(textbox.toLowerCase()) == -1) {
            if (!$(this).parent().is('span')) {
                $(this).wrap("<span>").hide();
            }
        }
        else {
            var span = $(this).parent();
            var opt = this;
            if ($(this).parent().is('span')) {
                $(opt).show();
                $(span).replaceWith(opt);
            }
        }
    });
}