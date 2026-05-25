from odoo import models, fields, exceptions

class evaluation_objectives(models.Model):
    _name = 'jtemployees.evaluation.objectives'
    _description = 'objectives for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name              = fields.Text(string='Objective', tracking=True)
    parent_id         = fields.Many2one('hr.employee', string='Parent', tracking=True)
    employee_id       = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True,
                        domain=lambda self: [('parent_id', '=', self.env.user.employee_id.id)],)
    deleted           = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    def create(self, vals_list):
        employees = self.env['hr.employee'].browse([vals_list['employee_id']])
        if self.env.user.employee_id.id != employees[0].parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
        vals_list['parent_id'] = employees[0].parent_id.id
        return super().create(vals_list)
    
    def write(self, values):
        if self.env.user.employee_id.id != self.employee_id.parent_id.id:
            raise exceptions.ValidationError("You are only allowed to create evaluations for your direct child employees")
        return super().write(values)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True