from odoo import models, fields, exceptions

class JTEmployeesGradesGroups(models.Model):
    _name = 'jtemployees.grades.groups'
    _description = 'Grades groups for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Char(
        string='Name',
        required=True,
        tracking=True
    )
    
    grades = fields.Many2many('jtemployees.grades', string='Grades', required=True, tracking=True)
    department = fields.Many2one('hr.department', string='Department', required=True, tracking=True)


