# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions, api
from odoo.osv.expression import AND, OR


class ssw_templates_tasks(models.Model):
    _name = 'ssw.proc.templates.tasks'
    _description = 'task Template'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')
    description = fields.Text()
        
    task_sequance            = fields.Integer(string='Task Sequance', tracking=True)
    template_id              = fields.Many2one('ssw.proc.templates', string='Template', tracking=True)
    next_task_ids            = fields.Many2many(comodel_name='ssw.proc.templates.tasks', relation='ssw_proc_template_task_next_rel', column1='task_id', column2='next_task_id', string='Next Tasks', domain="[('template_id', '=', template_id), ('enabled', '=', True), ('deleted', '=', False)]", tracking=True)
    default_department       = fields.Many2one('ssw.departments', string='Default department', required=True, tracking=True)
    departments_can_create   = fields.Many2many('ssw.departments', string='Departments can create', tracking=True)
    resolve_max_duration     = fields.Integer(string='SLA Duration hours', default=168, required=True, tracking=True)
    inputs                   = fields.One2many('ssw.proc.templates.inputs', 'template_id', string='Inputs', tracking=True, domain=[('deleted', '=', False)])
    default_group            = fields.Many2many('ssw.groups', string='Allowed group', help="If set, only users of those groups can view", tracking=True)
    contact_types_can_create = fields.Many2many('jtcontacts.ptype', string='Contact types can create', tracking=True)

    is_approve_only          = fields.Boolean(string='Is Approve task Only', default=False, tracking=True)
    
    has_procedures           = fields.Boolean(string='Has procedures', help='if set, the task\'s state will depend on the state of the inner procedures set to this ticket', default=False, tracking=True)
    ignore_state             = fields.Boolean(string='Ignore state', help='if set, the task\'s state will not affect anything. tasks after this task will not start, nor return will work.', default=False, tracking=True)
    sub_procedures           = fields.Many2many('ssw.proc.templates', string='Sub Procedures', help="The state of this task will depend on the state of those procedures", tracking=True)
    has_path_choice          = fields.Boolean(string='Has path choice', default=False, tracking=True)
    path_choice_question     = fields.Char(string='Path choice question', help='it will show as required question to the task handler', tracking=True)
    path_choices             = fields.One2many('ssw.proc.templates.taskpaths', 'task_id', string='Path Choices', help="Set path choices", tracking=True)

    flowchart_position_saved = fields.Boolean(string='Flowchart position saved', default=False, tracking=True)
    flowchart_x              = fields.Integer(string='Flowchart X', tracking=True)
    flowchart_y              = fields.Integer(string='Flowchart Y', tracking=True)
    
    visible_to_current_user  = fields.Boolean('Is this template visible to current user', compute='_set_visibility_to_user')

    is_contact_ticket_only   = fields.Boolean(string='Is Contact Ticket Only', help='If you set this to true it will not be visible to odoo users', default=False, tracking=True)
    enabled                  = fields.Boolean(string='Is Enabled', default=False, tracking=True)
    deleted                  = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    

    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    def _set_visibility_to_user(self):
        for record in self:
            current_user   = self.env.user
            task_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
            groups_can_see = task_user.groups_can_see.ids  # Get the IDs of the groups
            default_department_id = task_user.default_department.id
            record.visible_to_current_user = False
            if not record.deleted and record.enabled and not record.template_id and record.default_group.id in groups_can_see and default_department_id in record.sudo().departments_can_create.ids:
                record.visible_to_current_user = True
                
                
    def _get_template_domain(self):
        current_user   = self.env.user
        ticket_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        groups_can_see = ticket_user.groups_can_see.ids  # Get the IDs of the groups
        default_department_id = ticket_user.default_department.id
        
        contact_type_ids = current_user.contact_type.ids
        
        domain = [
            ('enabled', '=', True),
            ('deleted', '=', False),
            ('template_id', '=', False),
            ('is_contact_ticket_only', '=', False),
            ('default_group', 'in', groups_can_see),
            ('departments_can_create', 'in', [default_department_id])
        ]

        if contact_type_ids and len(contact_type_ids) > 0:
            domain.append(('contact_types_can_create', 'in', contact_type_ids))
        else:
            domain.append(('id', '=', 0))  # Ensures no record is returned
        
        return domain
    

    @api.model
    def web_search_read(self, domain=None, offset=0, limit=None, order=None, count_limit=None, specification=None):
        """Override the method called by Odoo's web client"""
        
        # Apply dynamic filtering when the context flag is set
        if self._context.get('apply_dynamic_domain'):
            dynamic_domain = self._get_template_domain()
            domain = domain or []
            domain = AND([domain, dynamic_domain])
        
        # Call the parent method with all parameters
        return super().web_search_read(
            domain=domain, 
            offset=offset, 
            limit=limit, 
            specification=specification, 
            order=order,
            count_limit=count_limit
        )
    
    def start_ticket(self):
        for record in self:
            if record.visible_to_current_user:
                return {
                    'name': 'Ticket Form',
                    'type': 'ir.actions.act_window',
                    'res_model': 'ssw.proc.tasks',
                    'view_mode': 'form',
                    'context': {
                        'default_task_id': record.id,
                        'create': True,
                    }
                }
            raise exceptions.ValidationError("You are not allowed to start this ticket.")
        return True
