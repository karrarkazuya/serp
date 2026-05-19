# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions


class ssw_managers(models.Model):
    _name = 'ssw.managers'
    _description = 'User'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    user_id                   = fields.Many2one('ssw.users', string='User', required=True, tracking=True)
    default_department        = fields.Many2many('ssw.departments', string='Departments to manage', tracking=True)
    deleted                   = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    def create(self, vals_list):
        item = super().create(vals_list)
        item.refresh_view_users()
        return item
    
    def write(self, values):
        response = super().write(values)
        self.refresh_view_users()
        return response
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    
    
    def refresh_view_users(self):
        for record in self:
        
            user = record.user_id
            
            # first tickets
            tickets = self.env['ssw.tickets'].sudo().search([('assigned_to_dep', 'in', record.default_department.ids)])
            for ticket in tickets:
                ticket.write({
                    'users_can_view': [(4, user.partner_id.id)]
                })
            # 2nd tasks and procedures
            tasks = self.env['ssw.proc.tasks'].sudo().search([('assigned_to_dep', 'in', record.default_department.ids)])
            for task in tasks:
                procedure = task.procedure_id
                task.update_users_view_only([(4, user.partner_id.id)])
                procedure.update_users_view_only([(4, user.partner_id.id)])
        
            