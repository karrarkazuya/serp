# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions, api
from odoo.osv.expression import AND, OR


class ssw_templates(models.Model):
    _name = 'ssw.tickets.templates'
    _description = 'Ticket Template'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')
    description = fields.Text()
    
    default_group            = fields.Many2one('ssw.groups', string='Allowed group', tracking=True)
    default_department       = fields.Many2one('ssw.departments', string='Default department', required=True, tracking=True)
    departments_can_create   = fields.Many2many('ssw.departments', string='Departments can create', tracking=True)
    contact_types_can_create = fields.Many2many('jtcontacts.ptype', string='Contact types can create', tracking=True)
    resolve_max_duration     = fields.Integer(string='SLA Duration hours', default=168, required=True, tracking=True)
    inputs                   = fields.One2many('ssw.tickets.templates.inputs', 'template_id', string='Inputs', tracking=True, domain=[('deleted', '=', False)])
    
    visible_to_current_user  = fields.Boolean('Is this template visible to current user', compute='_set_visibility_to_user')

    is_contact_ticket_only   = fields.Boolean(string='Is Contact Ticket Only', default=False, tracking=True)
    enabled                  = fields.Boolean(string='Is Enabled', default=False, tracking=True)
    deleted                  = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def _set_visibility_to_user(self):
        for record in self:
            current_user   = self.env.user
            tickets_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
            groups_can_see = tickets_user.groups_can_see.ids  # Get the IDs of the groups
            default_department_id = tickets_user.default_department.id
            record.visible_to_current_user = False
            if not record.deleted and record.enabled and record.default_group.id in groups_can_see and default_department_id in record.sudo().departments_can_create.ids:
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
            
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    def start_ticket(self):
        for record in self:
            if record.visible_to_current_user:
                return {
                    'name': 'Ticket Form',
                    'type': 'ir.actions.act_window',
                    'res_model': 'ssw.tickets',  # Target model
                    'view_mode': 'form',
                    'context': {
                        'default_template_id': record.id,
                        'create': True,
                    }
                }
            raise exceptions.ValidationError("You are not allowed to start this ticket.")
        return True
