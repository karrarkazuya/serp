from odoo import models, fields, exceptions

class jt_employee_holiday(models.Model):
    _name = 'jtemployees.holiday'
    _description = 'Holiday for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name             = fields.Char(string='Title', required=True, tracking=True)
    details          = fields.Text(string='Details', required=True, tracking=True)
    departments      = fields.Many2many('hr.department', string='Departments', required=True, tracking=True)
    date             = fields.Date(string='Date', default=False, tracking=True)
    deleted          = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
