# -*- coding: utf-8 -*-
{
    'name': 'HR',

    'summary': 'Advanced Employees App made specifically for Al-Jazeera telecom',

    'description': '''
        Advanced Employees App made specifically for Al-Jazeera telecom
    ''',

    'author': 'Jazeera Telecom',
    'category': 'Spring Modules',
    'version': '18.0.0.1.2',
    'license' : 'AGPL-3',

    # any module necessary for this one to work correctly
    'depends': ['base', 'mail', 'hr', 'hr_attendance', 'resource'],

    # always loaded
    'data': [
        'security/model_security.xml',
        'security/model_security_admin.xml',
        'security/model_security_hr_admin.xml',
        'security/model_security_hr_manager.xml',
        'security/model_security_team_manager.xml',
        'security/model_security_requests_approver.xml',
        'security/model_security_accountant.xml',
        
        'data/record_rules.xml',
        'data/mail_activity_type_data.xml',
        'crons/crons.xml',
        
        'views/resource/resource_calendar_view.xml',
        'views/resource/planned_days_view.xml',
        'views/resource/planned_rschedules_view.xml',
        'views/resource/resource_calendar_groups_view.xml',
        
        'views/evaluation/objectives_views.xml',
        'views/evaluation/values_views.xml',
        'views/evaluation/groups_views.xml',
        
        'views/points/points_views.xml',
        'views/points/groups_views.xml',
        'views/points/inputs_views.xml',
        'views/points/rewards_views.xml',
        'views/points/templates_views.xml',
        
        'views/templates.xml',
        'views/planned_days_templates.xml',
        
        'views/employees_views.xml',
        'views/employees_holiday_views.xml',
        'views/employees_wall_views.xml',
        
        'views/report/absence_views.xml',
        'views/report/general_pdf_manual.xml',
        'views/report/payroll_pdf_manual.xml',
        'views/report/employee_select_wizard.xml',
        'views/report/payroll_select_wizard.xml',
        
        'views/fingerprint/devices_views.xml',
        'views/fingerprint/log_views.xml',
        
        'views/grades/grades_views.xml',
        'views/grades/groups_views.xml',
        
        'views/payrolls/payrolls_views.xml',
        'views/payrolls/payrolls_slips_views.xml',
        'views/payrolls/payrolls_slips_details_views.xml',
        'views/payrolls/payrolls_slips_warnings_views.xml',
        'views/payrolls/payrolls_sub_details_views.xml',
        
        'views/employees_certificates_views.xml',
        'views/employees_images_views.xml',
        
        'views/dashboard_view.xml',
        
        'views/extra/allocations_views.xml',
        'views/extra/bounces_views.xml',
        'views/extra/shortages_views.xml',
        
        'views/employees_requests_views.xml',
        'views/employees_settings.xml',
        
        'views/location/area_views.xml',
        
        'views/subs/employees_subs_views.xml',
        'views/subs/employees_subs_childs_views.xml',
        'views/subs/employees_subs_childs_views.xml',
        'views/subs/planned_days_subs_templates.xml',
        
        'views/company.xml',
        'views/views.xml',
        'views/menus.xml'
    ],
    'assets': {
        'web.assets_backend': [
            'jtemployees/static/src/xml/dashboard.xml',
            'jtemployees/static/src/xml/sub_scheduler_planner_framer.xml',
            'jtemployees/static/src/xml/scheduler_planner_framer.xml',
            'jtemployees/static/src/js/dashboard.js',
            'jtemployees/static/src/js/action_tags.js',
            
            'jtemployees/static/src/xml/general_report.xml',
            'jtemployees/static/src/js/general_report.js',
            'jtemployees/static/src/xml/payroll_report.xml',
            'jtemployees/static/src/js/payroll_report.js',
        ],
    },
    'application': True,
    'installable': True,
    'post_init_hook': 'post_init_hook' # to handle the actions after the install

}

