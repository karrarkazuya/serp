# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions


class ssw_users(models.Model):
    _name = 'ssw.users'
    _description = 'User'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name                      = fields.Char(string='Title')
    user_id                   = fields.Many2one('res.users', string='Linked User', tracking=True)
    partner_id                = fields.Many2one('res.partner', string='Linked User', tracking=True)
    groups_can_see            = fields.Many2many('ssw.groups', string='Groups can see', tracking=True)
    departments_can_assign    = fields.Many2many('ssw.departments', string='Departments Can Assign', tracking=True)
    default_department        = fields.Many2one('ssw.departments', string='Default Department', tracking=True)
    deleted                   = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    def create(self, vals_list):
        item = super().create(vals_list)
        item.partner_id = item.user_id.partner_id.id
        item.refresh_view_users()
        return item
    
    def write(self, values):
        res = super().write(values)
        if self.user_id.partner_id.id != self.partner_id.id:
            self.partner_id = self.user_id.partner_id.id
        self.refresh_view_users()
        return res
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    
    def refresh_view_users(self):
        for user in self:
            # first tickets
            tickets = self.env['ssw.tickets'].sudo().search([('assigned_to_dep', '=', user.default_department.id)])
            for ticket in tickets:
                template = ticket.template_id
                if template.default_group.id in user.groups_can_see.ids:
                    ticket.write({
                        'users_can_view': [(4, user.partner_id.id)]
                    })
            # 2nd tasks and procedures
            tasks = self.env['ssw.proc.tasks'].sudo().search([('assigned_to_dep', '=', user.default_department.id)])
            for task in tasks:
                procedure = task.procedure_id
                template = task.procedure_id.template_id
                
                if template.default_group.id in user.groups_can_see.ids:
                    task.update_users_view_only([(4, user.partner_id.id)])
                    procedure.update_users_view_only([(4, user.partner_id.id)])
        