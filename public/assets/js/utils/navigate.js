let Navigate = {
    getPage: (elm, e)=>{
        e.preventDefault();
        let targetPage = $(elm).attr('data-target');
        let nav = $(elm).closest('ul.nav-data');
        $.each(nav.find('li'), function(){
            $(this).find('a').removeClass('active');
        });
        
        $.each(nav.find('li'), function(){
            if($(this).find('a').attr('data-target') == targetPage){
                $(this).find('a').addClass('active');
            }
        });

        let navContent = $('div.nav-data-content');
        $.each(navContent.find('div.nav-data-item'), function(){
            $(this).removeClass('hide');
        });
        
        $.each(navContent.find('div.nav-data-item'), function(){
            let dataId = $(this).attr('id');
            if(dataId != targetPage){
                $(this).addClass('hide');
            }
        });
    }
};