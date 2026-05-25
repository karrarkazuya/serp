from odoo import models, fields, exceptions

class JTEmployeesImages(models.Model):
    _name = 'jtemployees.wall'
    _description = 'Wall for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name        = fields.Char(string='Title', required=True, tracking=True)
    details     = fields.Char(string='Details', tracking=True)
    departments = fields.Many2many('hr.department', string='Departments', required=True, tracking=True)
    expires     = fields.Date(string='Expires at', required=True, tracking=True)
    image       = fields.Image(string='Image')
    deleted     = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    
    
    def create(self, vals_list):
        item = super().create(vals_list)
        
        for department in item.departments:
            for employee in department.member_ids:
                try:
                    mobileUser = self.env['jtapi.users'].sudo().search([('employee', '=', employee.id)], limit=1)
                    if mobileUser:
                        mobileUser._notify(item.name, item.details, "hr_wall", item.id, "jtemployees.wall")
                except:
                    pass
        return item
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
