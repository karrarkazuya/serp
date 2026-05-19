# -*- coding: utf-8 -*-
{
    'name': "Workflow",

    'summary': "Ticketing and Procedures",

    'description': """Ticketing  and Proceduressystem for support and issues resolving""",

    'author': "Spring Solutions",
    'website': "https://www.springsolutions.tech",

    # Categories can be used to filter modules in modules listing
    # Check https://github.com/odoo/odoo/blob/15.0/odoo/addons/base/data/ir_module_category_data.xml
    # for the full list
    'category': 'Spring Modules',
    'version': '18.0.0.1.2',
    'license' : 'AGPL-3',

    # any module necessary for this one to work correctly
    'depends': ['base', 'mail', 'jtapi', 'contacts', 'jtcontacts', 'purchase_stock'],

    # always loaded
    'data': [
        
        'crons/crons.xml',
        
        'security/model_security.xml',
        'security/model_security_admin.xml',
        'security/model_security_manager.xml',
        'security/model_security_submanager.xml',
        'security/model_security_viewer.xml',
        
        'data/record_rules.xml',
        
        'views/overrides/res_users.xml',
        'views/overrides/res_partner.xml',
        
        'views/tickets/tickets_views.xml',
        'views/tickets/tickets_inputs_views.xml',
        'views/tickets/templates_views.xml',
        #'views/tickets/templates_creation_views.xml',
        'views/tickets/templates_inputs_views.xml',
        'views/tickets/templates_inputs_subs_views.xml',
        
        'views/extra_models/product_line_views.xml',
        
        'views/procedures/procedures_views.xml',
        'views/procedures/task_return_wizard_views.xml',
        'views/procedures/task_return_to_wizard_views.xml',
        'views/procedures/start_wizard_views.xml',
        'views/procedures/tasks_views.xml',
        'views/procedures/tasks_paths_views.xml',
        'views/procedures/tasks_read_views.xml',
        'views/procedures/tasks_inputs_views.xml',
        'views/procedures/flowchart_templates.xml',
        'views/procedures/templates_views.xml',
        'views/procedures/templates_creation_views.xml',
        'views/procedures/templates_tasks_creation_views.xml',
        'views/procedures/templates_tasks_views.xml',
        'views/procedures/templates_tasks_paths_views.xml',
        'views/procedures/templates_inputs_views.xml',
        'views/procedures/templates_inputs_subs_views.xml',
        
        'views/departments_views.xml',
        'views/users_views.xml',
        'views/managers_views.xml',
        'views/groups_views.xml',
        'views/reports_pdf_manual.xml',
        'views/report_views.xml',
        'views/templates.xml',
        'views/dashboard.xml',
        'views/settings.xml',
        'views/menus.xml'
    ],
    'assets': {
        'web.assets_backend': [
            'ss_workflow/static/src/scss/procedure_templates.scss',
            'ss_workflow/static/src/scss/ticket_templates.scss',
            'ss_workflow/static/src/xml/activity_report.xml',
            'ss_workflow/static/src/js/activity_report.js',
            'ss_workflow/static/src/xml/procedure_performance_report.xml',
            'ss_workflow/static/src/js/procedure_performance_report.js',
            'ss_workflow/static/src/xml/ticket_performance_report.xml',
            'ss_workflow/static/src/js/ticket_performance_report.js',
            'ss_workflow/static/src/xml/task_performance_report.xml',
            'ss_workflow/static/src/js/task_performance_report.js',
            'ss_workflow/static/src/xml/dashboard.xml',
            'ss_workflow/static/src/js/dashboard.js'
        ],
    },
    'installable': True,
    'application': True,
    'post_init_hook': 'post_init_hook' # to handle the actions after the install
}
