# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions


class ssw_departments(models.Model):
    _name = 'ssw.departments'
    _description = 'Departments'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name                = fields.Char(string='Title', required=True, tracking=True)
    department_id       = fields.Integer(string='Department Id', tracking=True)
    company             = fields.Many2one('res.company', string='Company', required=True, tracking=True)
    deleted             = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
    
    def sync_departments(self):
        # First we set the default general types
        departments = self.env['hr.department'].sudo().search([])
        for department in departments:
            department_in_tickets = self.env['ssw.departments'].sudo().search([(
                "department_id", "=", department.id
            ), ('deleted', '=', False)], limit=1)
            if not department_in_tickets:
                company = department.company_id
                self.env['ssw.departments'].sudo().create({
                    "name": department.name + " ("+company.name+")",
                    "company": company.id,
                    "department_id": department.id
                })
            else:
                company = department.company_id
                department_in_tickets.name = department.name + " ("+company.name+")"
                
        # delete removed
        synced_departments = self.env['ssw.departments'].sudo().search([('deleted', '=', False)])
        for department in synced_departments:
            department_in_tickets = self.env['hr.department'].sudo().search([(
                "id", "=", department.department_id
            )], limit=1)
            if not department_in_tickets:
                department.unlink()
        
                
    
