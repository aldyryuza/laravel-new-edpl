let Template = {
    initUserLogin: () => {
        // let db = Database.init();
        // db.get('user_login').then(function (doc) {
        //     let user_login = $('#user_login');
        //     if (user_login.length > 0) {
        //         user_login.text(doc.title);
        //     }
        // }).then(function (response) {
        //     // handle response
        //     // console.log('response', response);
        // }).catch(function (err) {
        //     console.log('err', err);
        // });
    },

    logout: (elm, e) => {
        e.preventDefault();
        Database.removeDoc("user_login");
        Database.removeDoc("token");
        setTimeout(function () {
            window.location.href = $(elm).attr("href");
        }, 1500);
    },

    setLeftSideMenu: () => {
        let liMenuItem = $("li.menu-item");
        $.each(liMenuItem, function () {
            $(this).removeClass("open");
        });

        $.each(liMenuItem, function () {
            if ($(this).hasClass("active")) {
                let parentMenu = $(this).attr("parent_menu");
                // console.log('active', parentMenu);
                if (parentMenu != 0 && parentMenu != "") {
                    $(`#left-menu-${parentMenu}`).addClass("open");
                }
            }
        });

        //cek menu  single
        $.each(liMenuItem, function () {
            let dataId = $(this).attr("data_id");
            // console.log('dataId', dataId);
            let childMenu = $(`.parent-menu-${dataId}`);
            if (childMenu.length == 0) {
                $(this).find("a").removeClass("menu-toggle");
            }
        });
    },

    SearchMenuFunction: (obj_inp) => {
        let value_search = obj_inp.val().toLowerCase();
        // console.log(value_search)
        $(".menu-inner a").each(function () {
            if (value_search == "") {
                $(this).attr("style", "");
            } else {
                $(this).attr("style", "display : none;");
            }

            $(this).parent().removeClass("open");
            $(this).parent().parent().removeClass("active");

            $(this).parent().parent().parent().removeClass("open");
            $(this).parent().parent().parent().parent().removeClass("active");

            $(this)
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .removeClass("open");
            $(this)
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .removeClass("active");
        });

        $(".menu-inner a").each(function () {
            let menu_name = $(this).text().toLowerCase();
            // console.log("nama menunya :" + menu_name)
            if (value_search != "" && menu_name.includes(value_search)) {
                $(this).attr("style", "");
                $(this).parent().addClass("open");
                // $(this).parent().parent().addClass('active');
                $(this).parent().parent().prev().attr("style", "");

                $(this).parent().parent().parent().addClass("open");
                // $(this).parent().parent().parent().parent().addClass('active');
                $(this)
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .prev()
                    .attr("style", "");

                $(this)
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .addClass("open");
                // $(this).parent().parent().parent().parent().parent().parent().addClass('active');
                $(this)
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .parent()
                    .prev()
                    .attr("style", "");

                try {
                    let next_element = $(this).next();
                    if (next_element.prop("tagName").toLowerCase() == "ul") {
                        $(this).parent().removeClass("open");
                        // $(this).addClass('active');
                        next_element.find("li").each(function () {
                            $(this).children().attr("style", "");
                        });
                    }
                } catch (err) {}
            }
        });
    },
};

$(function () {
    // Template.initUserLogin();
    // $('#template-customizer').remove();
    Template.setLeftSideMenu();
});
