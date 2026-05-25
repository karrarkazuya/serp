from odoo import models, fields, exceptions

class evaluation_objectives(models.Model):
    _name = 'jtemployees.evaluation.values'
    _description = 'Values for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    group_id          = fields.Many2one('jtemployees.evaluation.groups', string='Group', required=True, tracking=True)
    objective         = fields.Many2one('jtemployees.evaluation.objectives', string='Objective', required=True, tracking=True)
    name              = fields.Text(string='Objective', tracking=True)
    note              = fields.Text(string='Note', tracking=True)
    parent_id         = fields.Many2one('hr.employee', string='Parent', tracking=True)
    employee_id       = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    percentage        = fields.Float(string='Percentage', default=0, required=True, tracking=True)
    deleted           = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    def create(self, vals_list):
        objective = self.env['jtemployees.evaluation.objectives'].sudo().search([
            (
                'id', '=', vals_list['objective']
            ),
            (
                'employee_id', '=', vals_list['employee_id']
            )
        ], limit=1)
        employees = self.env['hr.employee'].browse([vals_list['employee_id']])
        if self.env.user.employee_id.id != employees[0].parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
        vals_list['parent_id'] = employees[0].parent_id.id
        vals_list['name'] = objective.name
        if vals_list['percentage'] > 100 or vals_list['percentage'] < 0:
            raise exceptions.ValidationError("Bad percentage")
        return super().create(vals_list)
    
    
    def write(self, values):
        if self.env.user.employee_id.id != self.employee_id.parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
        return super().write(values)
    
    def reset_parent(self, employee_id, parent_id):
        
        values = self.env['jtemployees.evaluation.values'].sudo().search([
            (
                'parent_id', '!=', parent_id
            ),
            (
                'employee_id', '=', employee_id
            )
        ])
        
        for item in values:
            item.parent_id = parent_id
            if item.group_id.parent_id.id != parent_id:
                item.group_id.parent_id = parent_id
            if item.objective.parent_id.id != parent_id:
                item.objective.parent_id = parent_id
    
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True