

let Wizard = {
    nextWizard: (elm)=>{
        let stepperContent = $('div.bs-stepper-content');
        let stepHeader = $('div.bs-stepper-header');
        let nextTarget = $(elm).attr('next-target');
        // console.log('nextTarget', nextTarget);

        //header clean
        $.each(stepHeader.find('div.step'), function(){
            $(this).removeClass('active');
        });

        //content clean
        $.each(stepperContent.find('div.content'), function(){
            $(this).removeClass('active').removeClass('fv-plugins-bootstrap5').removeClass('fv-plugins-framework').removeClass('dstepper-block');
        });

        // set active
        stepHeader.find(`div.step[data-target="${nextTarget}"]`).addClass('active');
        stepperContent.find(`div#${nextTarget}`).addClass('active').addClass('fv-plugins-bootstrap5').addClass('fv-plugins-framework').addClass('dstepper-block');
    },

    prevWizard: (elm)=>{
        let stepperContent = $('div.bs-stepper-content');
        let stepHeader = $('div.bs-stepper-header');
        let prevTarget = $(elm).attr('prev-target');
        // console.log('nextTarget', nextTarget);

        //header clean
        $.each(stepHeader.find('div.step'), function(){
            $(this).removeClass('active');
        });

        //content clean
        $.each(stepperContent.find('div.content'), function(){
            $(this).removeClass('active').removeClass('fv-plugins-bootstrap5').removeClass('fv-plugins-framework').removeClass('dstepper-block');
        });

        // set active
        stepHeader.find(`div.step[data-target="${prevTarget}"]`).addClass('active');
        stepperContent.find(`div#${prevTarget}`).addClass('active').addClass('fv-plugins-bootstrap5').addClass('fv-plugins-framework').addClass('dstepper-block');
    },

    currentWizard: (elm)=>{
        let stepperContent = $('div.bs-stepper-content');
        let stepHeader = $('div.bs-stepper-header');
        let currentTarget = $(elm).attr('current-target');
        // console.log('nextTarget', nextTarget);

        //header clean
        $.each(stepHeader.find('div.step'), function(){
            $(this).removeClass('active');
        });

        //content clean
        $.each(stepperContent.find('div.content'), function(){
            $(this).removeClass('active').removeClass('fv-plugins-bootstrap5').removeClass('fv-plugins-framework').removeClass('dstepper-block');
        });

        // set active
        stepHeader.find(`div.step[data-target="${currentTarget}"]`).addClass('active');
        stepperContent.find(`div#${currentTarget}`).addClass('active').addClass('fv-plugins-bootstrap5').addClass('fv-plugins-framework').addClass('dstepper-block');
    }
};