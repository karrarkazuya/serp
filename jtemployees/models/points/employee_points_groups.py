from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta

class JTEmployeesPointsGroups(models.Model):
    _name = 'jtemployees.points.groups'
    _description = 'grouping for the points for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Text(
        string='Description',
        tracking=True
    )
    
    parent_id         = fields.Many2one('hr.employee', string='Submitted By', required=True, tracking=True) # parent manager
    point_ids         = fields.One2many('jtemployees.points', string='Evaluations', compute='_compute_points_of_group') # evaluations

    def create(self, vals):
        if self.env.user.employee_id:
            vals['parent_id'] = self.env.user.employee_id.id
        else:
            auth_user = self.env['jtapi.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1)
            employee = self.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            vals['parent_id'] = employee.id
            
        employees = self.env['hr.employee'].search([('parent_id', '=', vals['parent_id'])])
                
        group = super(JTEmployeesPointsGroups, self).create(vals)
        
        for employee in employees:
            try:
                self.env['jtemployees.points'].create({
                "name": vals['name'],
                "group_id": group.id,
                "parent_id": vals['parent_id'],
                "employee_id": employee.id
            })
            except:
                group.unlink()
                group = False
                raise exceptions.ValidationError("You have already submitted for this employee for this month")
        
        return group
    
    def unlink(self):
        for record in self:
            point_ids = self.env['jtemployees.points'].search([('group_id', '=', record.id)])
            for point in point_ids:
                point.unlink()
        super(JTEmployeesPointsGroups, self).unlink()
        return True
    
    @api.depends("parent_id")
    def _compute_points_of_group(self):
        for record in self:
            record.point_ids = self.env['jtemployees.points'].search([('group_id', '=', record.id)])
