from odoo import models, fields, exceptions, api

class JTEmployeesPointsInputs(models.Model):
    _name = 'jtemployees.points.inputs'
    _description = 'points for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    description = fields.Text(
        string='Info',
        tracking=True
    )
    
    title = fields.Char(
        string='Title',
        tracking=True
    )
    
    points_id       = fields.Many2one('jtemployees.points', string='Points', required=True, tracking=True)
    parent_id       = fields.Many2one('hr.employee', string='Parent', tracking=True)
    employee_id     = fields.Many2one('hr.employee', string='Emplpyee Id', tracking=True)
    is_hr           = fields.Boolean(string='HR Only', required=True, tracking=True)
    points          = fields.Integer(string='Points', required=True, tracking=True)
    
    
    @api.constrains('points')
    def _check_integer_field(self):
        for record in self:
            if not (0 <= record.points <= 10):
                raise exceptions.ValidationError("The value must be between 0 and 10")
            
    def write(self, vals):
        if any([item for item in ['parent_id', 'employee_id'] if item in vals]): # prevent modification
            raise exceptions.ValidationError("You are not allowed to modify this field")
        for record in self:
            if record.is_hr:
                if not ((self.env.user.has_group('jtemployees.group_admin') or self.env.user.has_group('jtemployees.group_hr_admin') or self.env.user.has_group('jtemployees.group_hr_manager'))):
                    raise exceptions.ValidationError("You are not allowed to modify this field")
            else:
                if self.env.user.employee_id:
                    parent_employee = self.env.user.employee_id
                else:
                    auth_user = self.env['jtapi.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1)
                    parent_employee = self.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
                if record.parent_id.id != parent_employee.id:
                    raise exceptions.ValidationError("You are not allowed to modify this field")
            
            points = self.env['jtemployees.points'].search([("id", "=", record.points_id.id)], limit=1)
            if not points:
                raise exceptions.ValidationError("You are not allowed to modify this field")
            if points and points.submitted:
                raise exceptions.ValidationError("You are not allowed to modify this field")
            vals['parent_id']   = points.parent_id.id
            vals['employee_id'] = points.employee_id.id
            super(JTEmployeesPointsInputs, self).write(vals)
        return True
