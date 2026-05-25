from odoo import models, fields, exceptions

class evaluation_groups(models.Model):
    _name = 'jtemployees.evaluation.groups'
    _description = 'Groups for evaluation Values for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    values_id         = fields.One2many('jtemployees.evaluation.values', 'group_id', string='Values', ondelete='cascade', tracking=True)
    parent_id         = fields.Many2one('hr.employee', string='Parent', tracking=True)
    employee_id       = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True, domain=lambda self: [('parent_id', '=', self.env.user.employee_id.id)])
    deleted           = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    def create(self, vals_list):
        my_employee = self.env.user.employee_id
        employees = self.env['hr.employee'].browse([vals_list['employee_id']])
        if my_employee.id != employees[0].parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
            
        vals_list['parent_id'] = employees[0].parent_id.id
        
        group = super().create(vals_list)
        
        objectives = self.env['jtemployees.evaluation.objectives'].sudo().search([('employee_id', '=', vals_list['employee_id']), ('deleted', '=', False)])
        for objective in objectives:
            payload = {
                "objective": objective.id,
                "employee_id": vals_list['employee_id'],
                "group_id": group.id,
                "percentage": 0
            }
            self.env['jtemployees.evaluation.values'].sudo().create(payload)
        return group
    
    def write(self, values):
        if 'employee_id' in values or 'parent_id' in values or 'create_date' in values:
            raise exceptions.ValidationError("You are only allowed to modify those fields")
        
        my_employee = self.env.user.employee_id
        employees = self.employee_id
        if my_employee.id != employees[0].parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
        
        return super().write(values)
    
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True